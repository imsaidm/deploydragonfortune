<?php

namespace App\Services\Signal;

use App\Repositories\MarketDataRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FeatureBuilder
{
    public function __construct(
        protected MarketDataRepository $marketData
    ) {
    }

    /**
     * Build a complete feature snapshot ready for scoring.
     * Now uses REAL database data for accurate signals.
     */
    public function build(string $symbol = 'BTC', string $pair = 'BTCUSDT', string $interval = '1h', ?int $timestampMs = null): array
    {
        $timestampMs = $timestampMs ?? now('UTC')->valueOf();
        $now = Carbon::createFromTimestampMs($timestampMs)->setTimezone('UTC');

        // Build each section independently - don't let one failure kill everything
        $funding = $this->safeBuilder(fn() => $this->buildFundingFeatures($pair, $timestampMs), 'funding');
        $openInterest = $this->safeBuilder(fn() => $this->buildOpenInterestFeatures($symbol, $interval, $timestampMs), 'open_interest');
        $whale = $this->safeBuilder(fn() => $this->buildWhaleFeatures($symbol, $now, $timestampMs), 'whales');
        $etf = $this->safeBuilder(fn() => $this->buildEtfFeatures($timestampMs), 'etf');
        $sentiment = $this->safeBuilder(fn() => $this->buildSentimentFeatures($timestampMs), 'sentiment');
        $micro = $this->safeBuilder(fn() => $this->buildMicrostructureFeatures($symbol, $pair, $interval, $timestampMs), 'microstructure');
        $liquidations = $this->safeBuilder(fn() => $this->buildLiquidationFeatures($symbol, $interval, $timestampMs), 'liquidations');
        $longShort = $this->safeBuilder(fn() => $this->buildLongShortFeatures($symbol, $interval, $timestampMs), 'long_short');
        $momentum = $this->safeBuilder(fn() => $this->buildMomentumFeatures($pair, $interval, $timestampMs), 'momentum');

        $sections = [
            'funding' => $funding,
            'open_interest' => $openInterest,
            'whales' => $whale,
            'etf' => $etf,
            'sentiment' => $sentiment,
            'microstructure' => $micro,
            'liquidations' => $liquidations,
            'long_short' => $longShort,
            'momentum' => $momentum,
        ];
        $health = $this->buildHealthSnapshot($sections);

        // If data completeness is very low, log warning but still use real data
        if (($health['completeness'] ?? 0) < 0.35) {
            Log::warning('Signal data completeness below 35%', [
                'symbol' => $symbol,
                'completeness' => $health['completeness'] ?? 0,
                'missing' => $health['missing_sections'] ?? [],
            ]);
        }

        return [
            'symbol' => strtoupper($symbol),
            'pair' => strtoupper($pair),
            'interval' => $interval,
            'generated_at' => $now->toIso8601ZuluString(),
            'funding' => $funding,
            'open_interest' => $openInterest,
            'whales' => $whale,
            'etf' => $etf,
            'sentiment' => $sentiment,
            'microstructure' => $micro,
            'liquidations' => $liquidations,
            'long_short' => $longShort,
            'momentum' => $momentum,
            'health' => $health,
        ];
    }

    /**
     * Safely build a feature section - returns empty array on failure instead of throwing
     */
    protected function safeBuilder(callable $builder, string $sectionName): array
    {
        try {
            return $builder();
        } catch (\Throwable $e) {
            Log::warning("FeatureBuilder {$sectionName} failed", [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function buildFundingFeatures(string $pair, ?int $timestampMs = null): array
    {
        $preferredInterval = '1h';
        $series = $this->marketData->latestFundingRates($pair, $preferredInterval, [], 200, $timestampMs);

        if ($series->isEmpty()) {
            $preferredInterval = '1m';
            $series = $this->marketData->latestFundingRates($pair, $preferredInterval, [], 500, $timestampMs);
        }

        $grouped = $series->groupBy('exchange');
        $exchangeSnapshots = $grouped->map(function (Collection $rows) {
            $latest = $rows->first();
            $window = $rows->take(60)->pluck('close')->map(fn ($value) => $this->toFloat($value));
            $mean = $window->avg();
            $std = $this->stdDev($window);
            $zScore = $this->zScore($this->toFloat($latest->close), $mean, $std);
            $ordered = $rows->sortByDesc('time')->values();
            $trendReference = $ordered->get(min($ordered->count() - 1, 3));
            $trend = $trendReference
                ? $this->percentChange($this->toFloat($ordered->first()->close), $this->toFloat($trendReference->close))
                : null;

            return [
                'latest' => $this->toFloat($latest->close),
                'mean' => $mean,
                'std' => $std,
                'z_score' => $zScore,
                'trend_pct' => $trend,
            ];
        });

        $heatScore = $exchangeSnapshots->avg('z_score');
        $latestConsensus = $exchangeSnapshots->avg('latest');
        $trendConsensus = $exchangeSnapshots->avg('trend_pct');

        return [
            'interval' => $preferredInterval,
            'heat_score' => $heatScore,
            'consensus' => $latestConsensus,
             'trend_pct' => $trendConsensus,
            'exchanges' => $exchangeSnapshots,
        ];
    }

    protected function buildOpenInterestFeatures(string $symbol, string $interval, ?int $timestampMs = null): array
    {
        $series = $this->marketData->latestOpenInterest($symbol, $interval, 'usd', 240, $timestampMs);

        if ($series->isEmpty()) {
            return [];
        }

        $latest = $series->first();
        $valuesAsc = $series->sortBy('time')->pluck('close')->map(fn ($v) => $this->toFloat($v));

        $ema = $this->ema($valuesAsc, 6);
        $pct6h = $this->percentChangeFromIndex($series, 6);
        $pct24h = $this->percentChangeFromIndex($series, 24);

        return [
            'latest' => $this->toFloat($latest->close),
            'pct_change_6h' => $pct6h,
            'pct_change_24h' => $pct24h,
            'ema_6' => $ema,
        ];
    }

    protected function buildWhaleFeatures(string $symbol, Carbon $now, ?int $timestampMs = null): array
    {
        $lookbackTs = $now->copy()->subDays(7)->timestamp;
        $upperBound = $timestampMs ? intdiv($timestampMs, 1000) : null;
        $raw = $this->marketData->latestWhaleTransfers($symbol, $lookbackTs, 2000, $upperBound);

        if ($raw->isEmpty()) {
            $raw = $this->marketData->latestWhaleTransfers($symbol, null, 2000, $upperBound);
        }

        if ($raw->isEmpty()) {
            return [
                'window_24h' => $this->aggregateWhaleFlows(collect()),
                'window_7d' => $this->aggregateWhaleFlows(collect()),
                'pressure_score' => null,
                'sample_size' => ['d24' => 0, 'd7' => 0],
                'is_stale' => true,
            ];
        }

        $lastDayTs = $now->copy()->subDay()->timestamp;
        $window7d = $raw->filter(fn ($row) => (int) $row->block_timestamp >= $lookbackTs);

        $stale = false;
        if ($window7d->isEmpty()) {
            $window7d = $raw;
            $stale = true;
        }

        $daily = $window7d->filter(fn ($row) => (int) $row->block_timestamp >= $lastDayTs);

        $agg7d = $this->aggregateWhaleFlows($window7d);
        $agg24h = $this->aggregateWhaleFlows($daily);

        $dayBuckets = max($window7d->map(fn ($row) => Carbon::createFromTimestamp($row->block_timestamp)->toDateString())->unique()->count(), 1);
        $avgDailyMagnitude = $dayBuckets > 0
            ? ($agg7d['inflow_usd'] + $agg7d['outflow_usd']) / $dayBuckets
            : 0.0;
        $baseline = max($avgDailyMagnitude, 1.0);
        $pressure = $agg24h['net_usd'] / $baseline;
        $activityTotal = ($agg24h['inflow_usd'] + $agg24h['outflow_usd']);
        $cexRatio = $activityTotal > 0 ? $agg24h['inflow_usd'] / $activityTotal : null;

        return [
            'window_24h' => $agg24h,
            'window_7d' => $agg7d,
            'pressure_score' => $pressure,
            'cex_ratio' => $cexRatio,
            'sample_size' => [
                'd24' => $daily->count(),
                'd7' => $window7d->count(),
            ],
            'is_stale' => $stale || $daily->isEmpty(),
        ];
    }

    protected function buildEtfFeatures(?int $timestampMs = null): array
    {
        $series = $this->marketData->latestEtfFlows(60, $timestampMs);

        if ($series->isEmpty()) {
            return [];
        }

        $latest = $series->first();
        $ma7 = $this->movingAverage($series, 7);
        $ma30 = $this->movingAverage($series, 30);
        $streak = 0;
        $direction = null;
        foreach ($series as $row) {
            $flow = $this->toFloat($row->flow_usd);
            if ($flow > 0) {
                if ($direction !== 'positive') {
                    $direction = 'positive';
                    $streak = 0;
                }
                $streak++;
            } elseif ($flow < 0) {
                if ($direction !== 'negative') {
                    $direction = 'negative';
                    $streak = 0;
                }
                $streak--;
            } else {
                break;
            }
        }

        return [
            'latest_flow' => $this->toFloat($latest->flow_usd),
            'ma7' => $ma7,
            'ma30' => $ma30,
            'streak' => $streak,
        ];
    }

    protected function buildSentimentFeatures(?int $timestampMs = null): array
    {
        $history = $this->marketData->fearGreedHistory(60, $timestampMs);

        if ($history->isEmpty()) {
            return [];
        }

        $latest = $history->first();

        return [
            'value' => (int) $latest->value,
            'classification' => $latest->value_classification,
            'ma7' => $history->take(7)->avg(fn ($row) => (int) $row->value),
            'ma30' => $history->take(30)->avg(fn ($row) => (int) $row->value),
        ];
    }

    protected function buildMicrostructureFeatures(string $symbol, string $pair, string $interval, ?int $timestampMs = null): array
    {
        // Orderbook table may not exist - handle gracefully
        $orderbook = collect();
        try {
            $orderbook = $this->marketData->latestSpotOrderbook($symbol, '1m', 120, $timestampMs);
        } catch (\Throwable $e) {
            // Table doesn't exist, continue without orderbook
        }
        
        $taker = $this->marketData->latestSpotTakerVolume($symbol, $interval, [], 120, $timestampMs);
        $prices = $this->marketData->latestSpotPrices($pair, $interval, 120, $timestampMs);

        $orderbookLatest = $orderbook->first();
        $takerLatest = $taker->first();
        $priceLatest = $prices->first();

        $takerAgg = $this->aggregateTakerVolumes($taker->take(24));
        $bidDepth = $orderbookLatest ? $this->toFloat($orderbookLatest->aggregated_bids_usd) : null;
        $askDepth = $orderbookLatest ? $this->toFloat($orderbookLatest->aggregated_asks_usd) : null;
        $imbalance = $this->orderbookImbalance($bidDepth, $askDepth);
        $volatility = $this->computeVolatility($prices);

        return [
            'orderbook' => [
                'bid_depth' => $bidDepth,
                'ask_depth' => $askDepth,
                'imbalance' => $imbalance,
                'bid_quantity' => $orderbookLatest ? $this->toFloat($orderbookLatest->aggregated_bids_quantity) : null,
                'ask_quantity' => $orderbookLatest ? $this->toFloat($orderbookLatest->aggregated_asks_quantity) : null,
            ],
            'taker_flow' => [
                'buy_volume' => $takerAgg['buy'],
                'sell_volume' => $takerAgg['sell'],
                'buy_ratio' => $takerAgg['ratio'],
            ],
            'price' => [
                'last_close' => $priceLatest ? $this->toFloat($priceLatest->close) : null,
                'pct_change_24h' => $this->percentChangeFromIndex($prices, 24),
                'volatility_24h' => $volatility,
            ],
        ];
    }

    protected function buildLiquidationFeatures(string $symbol, string $interval, ?int $timestampMs = null): array
    {
        $series = $this->marketData->latestLiquidations($symbol, $interval, 120, $timestampMs);

        if ($series->isEmpty()) {
            return [];
        }

        $latest = $series->first();
        $longTotal = $series->take(24)->sum(fn ($row) => $this->toFloat($row->aggregated_long_liquidation_usd));
        $shortTotal = $series->take(24)->sum(fn ($row) => $this->toFloat($row->aggregated_short_liquidation_usd));

        return [
            'latest' => [
                'longs' => $this->toFloat($latest->aggregated_long_liquidation_usd),
                'shorts' => $this->toFloat($latest->aggregated_short_liquidation_usd),
            ],
            'sum_24h' => [
                'longs' => $longTotal,
                'shorts' => $shortTotal,
            ],
        ];
    }

    protected function buildLongShortFeatures(string $symbol, string $interval, ?int $timestampMs = null): array
    {
        $global = $this->marketData->latestLongShortRatio($symbol, $interval, 'global', 240, $timestampMs);
        $top = $this->marketData->latestLongShortRatio($symbol, $interval, 'top', 240, $timestampMs);

        if ($global->isEmpty() && $top->isEmpty()) {
            return [];
        }

        $lookback = $this->lookbackFromInterval($interval);
        $globalSnapshot = $this->longShortSnapshot($global, $lookback);
        $topSnapshot = $this->longShortSnapshot($top, $lookback);

        $divergence = null;
        if ($globalSnapshot && $topSnapshot && $globalSnapshot['net_ratio'] !== null && $topSnapshot['net_ratio'] !== null) {
            $divergence = $topSnapshot['net_ratio'] - $globalSnapshot['net_ratio'];
        }

        $latestTimestamp = collect([
            $globalSnapshot['timestamp_ms'] ?? null,
            $topSnapshot['timestamp_ms'] ?? null,
        ])->filter()->max();

        $staleThreshold = 6 * 60 * 60 * 1000; // 6 jam
        $isStale = $latestTimestamp
            ? ($timestampMs - $latestTimestamp) > $staleThreshold
            : true;

        return [
            'global' => $this->presentLongShortSnapshot($globalSnapshot),
            'top' => $this->presentLongShortSnapshot($topSnapshot),
            'divergence' => $divergence,
            'bias' => [
                'global' => $this->biasLabel($globalSnapshot['net_ratio'] ?? null),
                'top' => $this->biasLabel($topSnapshot['net_ratio'] ?? null),
            ],
            'is_stale' => $isStale,
            'updated_at' => $latestTimestamp
                ? Carbon::createFromTimestampMs($latestTimestamp)->toIso8601ZuluString()
                : null,
        ];
    }

    protected function buildMomentumFeatures(string $pair, string $interval, ?int $timestampMs = null): array
    {
        $series = $this->marketData->latestSpotPrices($pair, '1h', 500, $timestampMs);

        if ($series->isEmpty()) {
            return [];
        }

        $moments = [
            'momentum_1h_pct' => $this->percentChangeFromIndex($series, 1),
            'momentum_4h_pct' => $this->percentChangeFromIndex($series, 4),
            'momentum_1d_pct' => $this->percentChangeFromIndex($series, 24),
            'momentum_7d_pct' => $this->percentChangeFromIndex($series, 24 * 7),
        ];

        $trendScore = $this->compositeTrendScore($moments);
        $volatility = $this->computeVolatility($series);
        $regime = $this->classifyRegime($trendScore, $volatility);
        $range = $this->spotRangeLevels($series, 48);

        return array_merge($moments, [
            'trend_score' => $trendScore,
            'volatility' => $volatility,
            'regime' => $regime['label'],
            'regime_reason' => $regime['reason'],
            'range' => $range,
        ]);
    }

    protected function compositeTrendScore(array $components): ?float
    {
        $weights = [
            'momentum_1h_pct' => 0.1,
            'momentum_4h_pct' => 0.2,
            'momentum_1d_pct' => 0.45,
            'momentum_7d_pct' => 0.25,
        ];

        $score = 0.0;
        $weightSum = 0.0;
        foreach ($weights as $key => $weight) {
            $value = $components[$key] ?? null;
            if ($value === null) {
                continue;
            }
            $score += $value * $weight;
            $weightSum += $weight;
        }

        return $weightSum > 0 ? $score / $weightSum : null;
    }

    protected function classifyRegime(?float $score, ?float $volatility): array
    {
        if ($score === null && $volatility === null) {
            return [
                'label' => 'UNKNOWN',
                'reason' => 'Momentum & volatility belum lengkap',
            ];
        }

        if ($score !== null && $score >= 1.5) {
            return [
                'label' => 'BULL TREND',
                'reason' => 'Momentum multi-timeframe mengarah naik',
            ];
        }

        if ($score !== null && $score <= -1.5) {
            return [
                'label' => 'BEAR TREND',
                'reason' => 'Momentum multi-timeframe melemah',
            ];
        }

        if ($volatility !== null && $volatility > 5 && abs($score ?? 0) < 1.0) {
            return [
                'label' => 'HIGH VOL CHOP',
                'reason' => 'Volatilitas tinggi tanpa arah jelas',
            ];
        }

        return [
            'label' => 'RANGE',
            'reason' => 'Momentum netral',
        ];
    }

    protected function spotRangeLevels(Collection $series, int $bars = 48): array
    {
        $subset = $series->take($bars);

        if ($subset->isEmpty()) {
            return [];
        }

        $high = $subset->max(fn ($row) => $this->toFloat($row->high ?? $row->close));
        $low = $subset->min(fn ($row) => $this->toFloat($row->low ?? $row->close));

        if ($high === null && $low === null) {
            return [];
        }

        $width = ($high !== null && $low !== null && $low != 0.0)
            ? (($high - $low) / $low) * 100
            : null;

        return [
            'high' => $high,
            'low' => $low,
            'width_pct' => $width,
        ];
    }

    protected function longShortSnapshot(Collection $rows, int $lookback): ?array
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $ordered = $rows->sortByDesc('time')->values();
        $latest = $ordered->first();

        $long = $this->toFloat($latest->long_account_ratio);
        $short = $this->toFloat($latest->short_account_ratio);
        $net = ($long !== null && $short !== null) ? $long - $short : null;

        $reference = $ordered->get(min($lookback, max($ordered->count() - 1, 0)));
        $change = null;
        if ($net !== null && $reference) {
            $prevLong = $this->toFloat($reference->long_account_ratio);
            $prevShort = $this->toFloat($reference->short_account_ratio);
            $prevNet = ($prevLong !== null && $prevShort !== null) ? $prevLong - $prevShort : null;
            if ($prevNet !== null && $prevNet != 0.0) {
                $change = (($net - $prevNet) / abs($prevNet)) * 100;
            }
        }

        return [
            'long_ratio' => $long,
            'short_ratio' => $short,
            'net_ratio' => $net,
            'change_24h_pct' => $change,
            'timestamp_ms' => $this->normalizeTimestamp($latest->time ?? null),
        ];
    }

    protected function presentLongShortSnapshot(?array $snapshot): ?array
    {
        if (!$snapshot) {
            return null;
        }

        return [
            'long_ratio' => $snapshot['long_ratio'],
            'short_ratio' => $snapshot['short_ratio'],
            'net_ratio' => $snapshot['net_ratio'],
            'change_24h_pct' => $snapshot['change_24h_pct'],
            'updated_at' => $snapshot['timestamp_ms']
                ? Carbon::createFromTimestampMs($snapshot['timestamp_ms'])->toIso8601ZuluString()
                : null,
        ];
    }

    protected function biasLabel(?float $net): ?string
    {
        if ($net === null) {
            return null;
        }

        if ($net > 0.03) {
            return 'LONG HEAVY';
        }

        if ($net < -0.03) {
            return 'SHORT HEAVY';
        }

        return 'BALANCED';
    }

    protected function lookbackFromInterval(string $interval): int
    {
        return match ($interval) {
            '4h' => 6,
            '1d' => 1,
            default => 24,
        };
    }

    protected function normalizeTimestamp(mixed $value): ?int
    {
        if ($value === null || !is_numeric($value)) {
            return null;
        }

        $numeric = (int) $value;
        if ($numeric < 1_000_000_000_000) {
            return $numeric * 1000;
        }

        return $numeric;
    }

    protected function buildHealthSnapshot(array $sections): array
    {
        $total = count($sections);
        if ($total === 0) {
            return [
                'completeness' => 0.0,
                'missing_sections' => [],
                'is_degraded' => true,
            ];
        }

        $missing = [];
        foreach ($sections as $key => $value) {
            if (empty($value)) {
                $missing[] = $key;
            }
        }

        $completeness = 1 - (count($missing) / $total);

        return [
            'completeness' => round($completeness, 2),
            'missing_sections' => $missing,
            'is_degraded' => $completeness < 0.7,
        ];
    }

    protected function percentChangeFromIndex(Collection $series, int $hours): ?float
    {
        if ($series->count() <= $hours) {
            return null;
        }

        $latest = $this->toFloat($series->first()->close);
        $reference = $this->toFloat($series->slice($hours, 1)->first()->close ?? null);

        return $this->percentChange($latest, $reference);
    }

    protected function percentChange(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null || $previous == 0.0) {
            return null;
        }

        return (($current - $previous) / $previous) * 100;
    }

    protected function ema(Collection $values, int $period): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $k = 2 / ($period + 1);
        $ema = $values->first();

        foreach ($values->slice(1) as $value) {
            $ema = ($value * $k) + ($ema * (1 - $k));
        }

        return $ema;
    }

    protected function movingAverage(Collection $series, int $length): ?float
    {
        if ($series->isEmpty()) {
            return null;
        }

        return $series->take($length)->avg(fn ($row) => $this->toFloat($row->flow_usd));
    }

    protected function stdDev(Collection $values): ?float
    {
        $values = $values->filter(fn ($value) => $value !== null);
        $count = $values->count();

        if ($count <= 1) {
            return null;
        }

        $mean = $values->avg();
        $variance = $values->map(fn ($value) => pow($value - $mean, 2))->sum() / ($count - 1);

        return sqrt($variance);
    }

    protected function zScore(?float $value, ?float $mean, ?float $std): ?float
    {
        if ($value === null || $mean === null || !$std) {
            return null;
        }

        return ($value - $mean) / $std;
    }

    protected function aggregateWhaleFlows(Collection $rows): array
    {
        $totals = [
            'inflow_usd' => 0.0,
            'outflow_usd' => 0.0,
            'count_inflow' => 0,
            'count_outflow' => 0,
        ];

        foreach ($rows as $row) {
            $amount = $this->toFloat($row->amount_usd);
            if ($this->isExchangeLabel($row->to_address)) {
                $totals['inflow_usd'] += $amount;
                $totals['count_inflow']++;
            } elseif ($this->isExchangeLabel($row->from_address)) {
                $totals['outflow_usd'] += $amount;
                $totals['count_outflow']++;
            }
        }

        $totals['net_usd'] = $totals['inflow_usd'] - $totals['outflow_usd'];

        return $totals;
    }

    protected function aggregateTakerVolumes(Collection $rows): array
    {
        $buy = $rows->sum(fn ($row) => $this->toFloat($row->aggregated_buy_volume_usd));
        $sell = $rows->sum(fn ($row) => $this->toFloat($row->aggregated_sell_volume_usd));
        $total = $buy + $sell;

        return [
            'buy' => $buy,
            'sell' => $sell,
            'ratio' => $total > 0 ? $buy / $total : null,
        ];
    }

    protected function orderbookImbalance(?float $bid, ?float $ask): ?float
    {
        if ($bid === null || $ask === null || ($bid + $ask) == 0.0) {
            return null;
        }

        return ($bid - $ask) / ($bid + $ask);
    }

    protected function computeVolatility(Collection $prices): ?float
    {
        if ($prices->count() < 2) {
            return null;
        }

        $ordered = $prices->sortByDesc('time')->values();
        $returns = [];
        $limit = min(24, $ordered->count() - 1);
        for ($i = 0; $i < $limit; $i++) {
            $current = $this->toFloat($ordered[$i]->close);
            $previous = $this->toFloat($ordered[$i + 1]->close ?? null);
            $change = $this->percentChange($current, $previous);
            if ($change !== null) {
                $returns[] = $change;
            }
        }

        if (empty($returns)) {
            return null;
        }

        return $this->stdDev(collect($returns));
    }

    protected function toFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    protected array $exchangeKeywords = [
        'binance',
        'coinbase',
        'kraken',
        'bitfinex',
        'bitstamp',
        'bybit',
        'okx',
        'okex',
        'deribit',
        'kucoin',
        'mexc',
        'huobi',
        'gate',
        'gemini',
    ];

    protected function isExchangeLabel(?string $label): bool
    {
        if (!$label) {
            return false;
        }

        $label = Str::lower($label);

        foreach ($this->exchangeKeywords as $keyword) {
            if (Str::contains($label, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build mock features for development/testing
     */
    protected function buildMockFeatures(string $symbol, string $pair, string $interval, Carbon $now): array
    {
        $price = $symbol === 'BTC' ? 96500 : ($symbol === 'ETH' ? 3600 : 180);
        $volatility = 2.5 + (rand(-10, 10) / 10);
        
        return [
            'symbol' => strtoupper($symbol),
            'pair' => strtoupper($pair),
            'interval' => $interval,
            'generated_at' => $now->toIso8601ZuluString(),
            'funding' => [
                'heat_score' => 0.35 + (rand(-20, 20) / 100),
                'latest_consensus' => 0.0085,
                'trend_pct' => rand(-5, 5),
                'is_stale' => false,
            ],
            'open_interest' => [
                'pct_change_24h' => rand(-3, 5),
                'total_oi_usd' => 12500000000 + rand(-1000000000, 2000000000),
                'is_stale' => false,
            ],
            'whales' => [
                'pressure_score' => rand(-15, 15) / 10,
                'cex_ratio' => 0.45 + (rand(-10, 10) / 100),
                'is_stale' => false,
                'window_24h' => [
                    'inflow_usd' => 125000000 + rand(-20000000, 50000000),
                    'outflow_usd' => 98000000 + rand(-15000000, 40000000),
                    'net_usd' => 27000000 + rand(-10000000, 20000000),
                    'count_inflow' => rand(15, 45),
                    'count_outflow' => rand(12, 38),
                ],
            ],
            'etf' => [
                'latest_flow' => rand(-500, 800) * 1000000,
                'streak' => rand(-4, 6),
                'is_stale' => false,
            ],
            'sentiment' => [
                'value' => rand(35, 75),
                'label' => rand(0, 1) ? 'Fear' : 'Greed',
                'is_stale' => false,
            ],
            'microstructure' => [
                'price' => [
                    'last_close' => $price + rand(-500, 500),
                    'volatility_24h' => $volatility,
                    'volume_24h' => 28500000000 + rand(-2000000000, 5000000000),
                    'pct_change_24h' => rand(-5, 6),
                ],
                'taker_flow' => [
                    'buy_ratio' => 0.48 + (rand(-5, 10) / 100),
                    'sell_ratio' => 0.52 + (rand(-10, 5) / 100),
                ],
                'is_stale' => false,
            ],
            'liquidations' => [
                'sum_24h' => [
                    'longs' => 85000000 + rand(-10000000, 30000000),
                    'shorts' => 67000000 + rand(-8000000, 25000000),
                ],
                'is_stale' => false,
            ],
            'long_short' => [
                'global' => [
                    'long_pct' => 0.48 + (rand(-5, 10) / 100),
                    'short_pct' => 0.52 + (rand(-10, 5) / 100),
                    'net_ratio' => rand(-8, 8) / 100,
                ],
                'top' => [
                    'long_pct' => 0.52 + (rand(-5, 8) / 100),
                    'short_pct' => 0.48 + (rand(-8, 5) / 100),
                    'net_ratio' => rand(-5, 12) / 100,
                ],
                'divergence' => rand(-8, 12) / 100,
                'is_stale' => false,
            ],
            'momentum' => [
                'trend_score' => rand(-15, 25) / 10,
                'momentum_1d_pct' => rand(-4, 6),
                'momentum_7d_pct' => rand(-10, 15),
                'regime_reason' => rand(0, 1) ? 'Bull momentum detected' : 'Range-bound market',
                'range' => [
                    'width_pct' => rand(2, 8),
                ],
            ],
            'health' => [
                'completeness' => 0.95,
                'missing_sections' => [],
                'is_degraded' => false,
            ],
        ];
    }
}
