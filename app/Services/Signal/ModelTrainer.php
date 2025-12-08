<?php

namespace App\Services\Signal;

use Illuminate\Support\Facades\Storage;

class ModelTrainer
{
    protected array $featureNames = [
        'funding_heat',
        'funding_trend',
        'oi_pct_change_24h',
        'oi_pct_change_6h',
        'whale_pressure',
        'whale_cex_ratio',
        'etf_flow_normalized',
        'etf_streak',
        'sentiment_normalized',
        'taker_ratio',
        'orderbook_imbalance',
        'liquidation_bias',
        'volatility_24h',
        'momentum_1d',
        'momentum_7d',
        'trend_score',
        'long_short_global',
        'long_short_top',
        'long_short_divergence',
    ];

    protected string $modelPath = 'signal-model.json';

    public function train(array $dataset, array $labels, int $epochs = 300, float $learningRate = 0.01): ?array
    {
        if (count($dataset) < 20) {
            return null;
        }

        $featureCount = count($this->featureNames);
        $weights = array_fill(0, $featureCount + 1, 0.0); // bias + features
        $n = count($dataset);

        for ($epoch = 0; $epoch < $epochs; $epoch++) {
            $gradients = array_fill(0, $featureCount + 1, 0.0);

            for ($i = 0; $i < $n; $i++) {
                $x = array_merge([1.0], $dataset[$i]);
                $y = $labels[$i];
                $prediction = $this->sigmoid($this->dot($weights, $x));
                $error = $prediction - $y;

                foreach ($gradients as $j => $_) {
                    $gradients[$j] += $error * $x[$j];
                }
            }

            foreach ($weights as $j => $weight) {
                $weights[$j] -= $learningRate * ($gradients[$j] / $n);
            }
        }

        return [
            'feature_names' => $this->featureNames,
            'weights' => $weights,
            'trained_at' => now('UTC')->toIso8601String(),
            'epochs' => $epochs,
            'learning_rate' => $learningRate,
        ];
    }

    public function saveModel(array $model): void
    {
        Storage::disk('local')->put($this->modelPath, json_encode($model));
    }

    public function loadModel(): ?array
    {
        if (!Storage::disk('local')->exists($this->modelPath)) {
            return null;
        }

        return json_decode(Storage::disk('local')->get($this->modelPath), true);
    }

    public function extractFeatureVector(array $payload): ?array
    {
        // Core features
        $funding = data_get($payload, 'funding.heat_score');
        $fundingTrend = data_get($payload, 'funding.trend_pct');
        $oi24h = data_get($payload, 'open_interest.pct_change_24h');
        $oi6h = data_get($payload, 'open_interest.pct_change_6h');
        $whale = data_get($payload, 'whales.pressure_score');
        $whaleCex = data_get($payload, 'whales.cex_ratio');
        $etf = data_get($payload, 'etf.latest_flow');
        $etfStreak = data_get($payload, 'etf.streak');
        $sentiment = data_get($payload, 'sentiment.value');
        $taker = data_get($payload, 'microstructure.taker_flow.buy_ratio');
        $orderImbalance = data_get($payload, 'microstructure.orderbook.imbalance');
        $longs = data_get($payload, 'liquidations.sum_24h.longs');
        $shorts = data_get($payload, 'liquidations.sum_24h.shorts');
        $volatility = data_get($payload, 'microstructure.price.volatility_24h');
        
        // Momentum features
        $momentum1d = data_get($payload, 'momentum.momentum_1d_pct');
        $momentum7d = data_get($payload, 'momentum.momentum_7d_pct');
        $trendScore = data_get($payload, 'momentum.trend_score');
        
        // Long/short features
        $lsGlobal = data_get($payload, 'long_short.global.net_ratio');
        $lsTop = data_get($payload, 'long_short.top.net_ratio');
        $lsDivergence = data_get($payload, 'long_short.divergence');

        // Need at least some core data
        $hasMinData = $funding !== null || $oi24h !== null || $whale !== null || $trendScore !== null;
        if (!$hasMinData) {
            return null;
        }

        // Calculate liquidation bias (positive = more shorts liquidated = bullish)
        $liquidationBias = 0.0;
        if ($longs !== null && $shorts !== null && ($longs + $shorts) > 0) {
            $liquidationBias = ($shorts - $longs) / ($shorts + $longs);
        }

        return [
            $this->normalize($funding, 3),              // funding_heat
            $this->normalize($fundingTrend, 100),       // funding_trend
            $this->normalize($oi24h, 50),               // oi_pct_change_24h
            $this->normalize($oi6h, 30),                // oi_pct_change_6h
            $this->normalize($whale, 3),                // whale_pressure
            $whaleCex ?? 0.5,                           // whale_cex_ratio
            $this->normalize($etf, 500_000_000),        // etf_flow_normalized (scaled for billions)
            $this->normalize($etfStreak, 10),           // etf_streak
            $this->normalize($sentiment, 100),          // sentiment_normalized
            $taker ?? 0.5,                              // taker_ratio
            $orderImbalance ?? 0.0,                     // orderbook_imbalance
            $liquidationBias,                           // liquidation_bias
            $this->normalize($volatility, 10),          // volatility_24h
            $this->normalize($momentum1d, 10),          // momentum_1d
            $this->normalize($momentum7d, 30),          // momentum_7d
            $this->normalize($trendScore, 5),           // trend_score
            $this->normalize($lsGlobal, 0.2),           // long_short_global
            $this->normalize($lsTop, 0.2),              // long_short_top
            $this->normalize($lsDivergence, 0.15),      // long_short_divergence
        ];
    }

    public function predict(array $payload): ?float
    {
        $model = $this->loadModel();
        if (!$model) {
            return null;
        }

        $vector = $this->extractFeatureVector($payload);
        if (!$vector) {
            return null;
        }

        $weights = $model['weights'];
        $x = array_merge([1.0], $vector);

        return $this->sigmoid($this->dot($weights, $x));
    }

    protected function normalize($value, float $scale): float
    {
        if ($value === null) {
            return 0.0;
        }
        return (float) $value / $scale;
    }

    protected function dot(array $weights, array $features): float
    {
        $sum = 0.0;
        foreach ($weights as $i => $weight) {
            $sum += $weight * ($features[$i] ?? 0.0);
        }

        return $sum;
    }

    protected function sigmoid(float $z): float
    {
        return 1 / (1 + exp(-$z));
    }
}
