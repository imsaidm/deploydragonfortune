<?php

namespace App\Services\Signal;

class SignalEngine
{
    public function score(array $features): array
    {
        $score = 0.0;
        $reasons = [];
        $factors = [];

        $fundingHeat = $features['funding']['heat_score'] ?? null;
        $fundingConsensus = $features['funding']['consensus'] ?? null;
        $fundingTrend = $features['funding']['trend_pct'] ?? null;
        $oiPct24 = $features['open_interest']['pct_change_24h'] ?? null;
        $oiPct6 = $features['open_interest']['pct_change_6h'] ?? null;
        $whalePressure = $features['whales']['pressure_score'] ?? null;
        $whaleCexRatio = $features['whales']['cex_ratio'] ?? null;
        $etfFlow = $features['etf']['latest_flow'] ?? null;
        $etfMa7 = $features['etf']['ma7'] ?? null;
        $etfStreak = $features['etf']['streak'] ?? null;
        $sentimentValue = $features['sentiment']['value'] ?? null;
        $takerRatio = $features['microstructure']['taker_flow']['buy_ratio'] ?? null;
        $orderImbalance = $features['microstructure']['orderbook']['imbalance'] ?? null;
        $volatility = $features['microstructure']['price']['volatility_24h'] ?? null;
        $liq = $features['liquidations']['sum_24h'] ?? null;
        $longShortGlobal = data_get($features, 'long_short.global.net_ratio');
        $longShortTop = data_get($features, 'long_short.top.net_ratio');
        $longShortDiv = data_get($features, 'long_short.divergence');
        $longShortStale = data_get($features, 'long_short.is_stale');
        $momentumTrend = data_get($features, 'momentum.trend_score');
        $momentumRegime = data_get($features, 'momentum.regime');
        $momentum1d = data_get($features, 'momentum.momentum_1d_pct');
        $momentum7d = data_get($features, 'momentum.momentum_7d_pct');
        $rangeWidth = data_get($features, 'momentum.range.width_pct');

        $this->contribute(
            $fundingHeat !== null && $fundingHeat > 1.5,
            -2,
            "Funding overheated (z {$this->formatFloat($fundingHeat)})",
            $score,
            $reasons,
            $factors,
            ['heat' => $fundingHeat, 'consensus' => $fundingConsensus]
        );

        $this->contribute(
            $fundingHeat !== null && $fundingHeat < -1.5,
            2,
            "Funding deeply discounted (z {$this->formatFloat($fundingHeat)})",
            $score,
            $reasons,
            $factors,
            ['heat' => $fundingHeat, 'consensus' => $fundingConsensus]
        );

        $this->contribute(
            $fundingTrend !== null && $fundingTrend > 15,
            0.6,
            'Funding momentum turning higher',
            $score,
            $reasons,
            $factors,
            ['trend_pct' => $fundingTrend]
        );

        $this->contribute(
            $fundingTrend !== null && $fundingTrend < -15,
            -0.6,
            'Funding momentum rolling over',
            $score,
            $reasons,
            $factors,
            ['trend_pct' => $fundingTrend]
        );

        $this->contribute(
            $oiPct24 !== null && $fundingHeat !== null && $oiPct24 > 2 && $fundingHeat > 0.5,
            -1.5,
            'Leverage build-up with positive funding',
            $score,
            $reasons,
            $factors,
            ['oi_pct_24h' => $oiPct24, 'funding_heat' => $fundingHeat]
        );

        $this->contribute(
            $oiPct24 !== null && $oiPct24 < -2,
            1.0,
            'Open interest flushing (de-leverage)',
            $score,
            $reasons,
            $factors,
            ['oi_pct_24h' => $oiPct24]
        );

        $this->contribute(
            $whalePressure !== null && $whalePressure > 1.2,
            -1.5,
            'Whale inflow into exchanges',
            $score,
            $reasons,
            $factors,
            ['pressure_score' => $whalePressure]
        );

        $this->contribute(
            $whalePressure !== null && $whalePressure < -1.2,
            1.5,
            'Whale accumulation off-exchange',
            $score,
            $reasons,
            $factors,
            ['pressure_score' => $whalePressure]
        );

        $this->contribute(
            $whaleCexRatio !== null && $whaleCexRatio > 0.65,
            -0.6,
            'Whale inflow concentrated on exchanges',
            $score,
            $reasons,
            $factors,
            ['cex_ratio' => $whaleCexRatio]
        );

        $this->contribute(
            $whaleCexRatio !== null && $whaleCexRatio < 0.35,
            0.6,
            'Whales distributing to cold storage',
            $score,
            $reasons,
            $factors,
            ['cex_ratio' => $whaleCexRatio]
        );

        $this->contribute(
            $etfFlow !== null && $etfFlow > 0 && $etfMa7 !== null && $etfFlow > $etfMa7,
            1.2,
            'ETF net inflow above weekly average',
            $score,
            $reasons,
            $factors,
            ['latest_flow' => $etfFlow, 'ma7' => $etfMa7]
        );

        $this->contribute(
            $etfFlow !== null && $etfFlow < 0 && $etfMa7 !== null && $etfFlow < $etfMa7,
            -1.2,
            'ETF outflow pressure',
            $score,
            $reasons,
            $factors,
            ['latest_flow' => $etfFlow, 'ma7' => $etfMa7]
        );

        $this->contribute(
            $etfStreak !== null && $etfStreak >= 3,
            0.9,
            'ETF inflow streak',
            $score,
            $reasons,
            $factors,
            ['streak' => $etfStreak]
        );

        $this->contribute(
            $etfStreak !== null && $etfStreak <= -3,
            -0.9,
            'ETF outflow streak',
            $score,
            $reasons,
            $factors,
            ['streak' => $etfStreak]
        );

        $this->contribute(
            $sentimentValue !== null && $sentimentValue >= 70,
            -1.0,
            'Extreme greed zone',
            $score,
            $reasons,
            $factors,
            ['sentiment' => $sentimentValue]
        );

        $this->contribute(
            $sentimentValue !== null && $sentimentValue <= 30,
            1.0,
            'Fear zone (contrarian bullish)',
            $score,
            $reasons,
            $factors,
            ['sentiment' => $sentimentValue]
        );

        $this->contribute(
            $takerRatio !== null && $takerRatio > 0.55,
            0.8,
            'Aggressive buyers dominating order flow',
            $score,
            $reasons,
            $factors,
            ['taker_buy_ratio' => $takerRatio]
        );

        $this->contribute(
            $takerRatio !== null && $takerRatio < 0.45,
            -0.8,
            'Aggressive sellers dominating order flow',
            $score,
            $reasons,
            $factors,
            ['taker_buy_ratio' => $takerRatio]
        );

        $this->contribute(
            $orderImbalance !== null && $orderImbalance > 0.1,
            0.5,
            'Bid-side liquidity stacked',
            $score,
            $reasons,
            $factors,
            ['orderbook_imbalance' => $orderImbalance]
        );

        $this->contribute(
            $orderImbalance !== null && $orderImbalance < -0.1,
            -0.5,
            'Ask-side liquidity stacked',
            $score,
            $reasons,
            $factors,
            ['orderbook_imbalance' => $orderImbalance]
        );

        $this->contribute(
            $volatility !== null && $volatility > 5 && $takerRatio !== null && $takerRatio < 0.45,
            -0.6,
            'High volatility with aggressive sellers',
            $score,
            $reasons,
            $factors,
            ['volatility_24h' => $volatility, 'taker_buy_ratio' => $takerRatio]
        );

        $this->contribute(
            $volatility !== null && $volatility < 1.5 && $takerRatio !== null && $takerRatio > 0.55,
            0.5,
            'Calm flow with buyers in control',
            $score,
            $reasons,
            $factors,
            ['volatility_24h' => $volatility, 'taker_buy_ratio' => $takerRatio]
        );

        if ($liq) {
            $longs = $liq['longs'] ?? null;
            $shorts = $liq['shorts'] ?? null;
            $this->contribute(
                $longs !== null && $shorts !== null && $longs > $shorts * 1.5,
                0.8,
                'Long liquidation flush (potential rebound)',
                $score,
                $reasons,
                $factors,
                ['long_liq_24h' => $longs, 'short_liq_24h' => $shorts]
            );
            $this->contribute(
                $longs !== null && $shorts !== null && $shorts > $longs * 1.5,
                -0.8,
                'Short liquidation spike (potential exhaustion)',
                $score,
                $reasons,
                $factors,
                ['long_liq_24h' => $longs, 'short_liq_24h' => $shorts]
            );
        }

        $this->contribute(
            $momentumTrend !== null && $momentumTrend > 1.2,
            1.1,
            'Momentum multi-timeframe bullish (score ' . $this->formatFloat($momentumTrend) . ')',
            $score,
            $reasons,
            $factors,
            ['trend_score' => $momentumTrend]
        );

        $this->contribute(
            $momentumTrend !== null && $momentumTrend < -1.2,
            -1.1,
            'Momentum multi-timeframe bearish (score ' . $this->formatFloat($momentumTrend) . ')',
            $score,
            $reasons,
            $factors,
            ['trend_score' => $momentumTrend]
        );

        $this->contribute(
            $momentum1d !== null && $momentum1d > 2.0,
            0.6,
            'Price impulse +2% dalam 24 jam',
            $score,
            $reasons,
            $factors,
            ['momentum_1d_pct' => $momentum1d]
        );

        $this->contribute(
            $momentum1d !== null && $momentum1d < -2.0,
            -0.6,
            'Price dump -2% dalam 24 jam',
            $score,
            $reasons,
            $factors,
            ['momentum_1d_pct' => $momentum1d]
        );

        $this->contribute(
            $momentum7d !== null && $momentum7d > 5,
            0.4,
            'Trend 7 hari masih naik',
            $score,
            $reasons,
            $factors,
            ['momentum_7d_pct' => $momentum7d]
        );

        $this->contribute(
            $momentum7d !== null && $momentum7d < -5,
            -0.4,
            'Trend 7 hari turun tajam',
            $score,
            $reasons,
            $factors,
            ['momentum_7d_pct' => $momentum7d]
        );

        $this->contribute(
            $longShortGlobal !== null && $longShortGlobal > 0.04,
            0.7,
            'Mayoritas akun global net long',
            $score,
            $reasons,
            $factors,
            ['net_ratio' => $longShortGlobal]
        );

        $this->contribute(
            $longShortGlobal !== null && $longShortGlobal < -0.04,
            -0.7,
            'Mayoritas akun global net short',
            $score,
            $reasons,
            $factors,
            ['net_ratio' => $longShortGlobal]
        );

        $this->contribute(
            $longShortTop !== null && $longShortTop > 0.06,
            0.9,
            'Top trader agresif long',
            $score,
            $reasons,
            $factors,
            ['top_net_ratio' => $longShortTop]
        );

        $this->contribute(
            $longShortTop !== null && $longShortTop < -0.06,
            -0.9,
            'Top trader agresif short',
            $score,
            $reasons,
            $factors,
            ['top_net_ratio' => $longShortTop]
        );

        $this->contribute(
            $longShortDiv !== null && $longShortDiv > 0.05,
            0.5,
            'Divergensi smart money long',
            $score,
            $reasons,
            $factors,
            ['divergence' => $longShortDiv]
        );

        $this->contribute(
            $longShortDiv !== null && $longShortDiv < -0.05,
            -0.5,
            'Divergensi smart money short',
            $score,
            $reasons,
            $factors,
            ['divergence' => $longShortDiv]
        );

        $this->contribute(
            $rangeWidth !== null && $rangeWidth < 1.5,
            -0.4,
            'Range harga sempit, fakeout risk',
            $score,
            $reasons,
            $factors,
            ['range_width_pct' => $rangeWidth]
        );

        $this->contribute(
            $rangeWidth !== null && $rangeWidth > 6 && $momentumTrend !== null && abs($momentumTrend) > 1,
            0.3,
            'Range luas mendukung breakout searah momentum',
            $score,
            $reasons,
            $factors,
            ['range_width_pct' => $rangeWidth, 'trend_score' => $momentumTrend]
        );

        // Apply confluence bonus - signals are stronger when multiple factors align
        $confluenceBonus = $this->calculateConfluenceBonus($factors);
        $adjustedScore = $score + $confluenceBonus;

        $signal = $this->determineSignal($adjustedScore, $volatility, $features);
        $confidence = min(abs($adjustedScore) / 5, 1);
        
        // Boost confidence when high-quality confluence exists
        if (count($factors) >= 5 && abs($adjustedScore) >= 2.5) {
            $confidence = min($confidence * 1.15, 1.0);
        }
        
        $quality = $this->buildQualitySummary($adjustedScore, $features, $volatility);
        $meta = [
            'regime' => $momentumRegime,
            'regime_reason' => data_get($features, 'momentum.regime_reason'),
            'long_short_bias' => data_get($features, 'long_short.bias.global'),
            'top_trader_bias' => data_get($features, 'long_short.bias.top'),
            'confluence_factors' => count($factors),
            'confluence_bonus' => $confluenceBonus,
        ];

        return [
            'signal' => $signal,
            'score' => round($adjustedScore, 2),
            'raw_score' => round($score, 2),
            'confidence' => round($confidence, 3),
            'reasons' => $reasons,
            'factors' => $factors,
            'quality' => $quality,
            'meta' => $meta,
        ];
    }

    /**
     * Calculate bonus score when multiple high-quality factors align
     */
    protected function calculateConfluenceBonus(array $factors): float
    {
        $bullishCount = 0;
        $bearishCount = 0;
        $strongBullish = 0;
        $strongBearish = 0;
        
        foreach ($factors as $factor) {
            $weight = $factor['weight'] ?? 0;
            if ($weight > 0) {
                $bullishCount++;
                if ($weight >= 1.0) $strongBullish++;
            } elseif ($weight < 0) {
                $bearishCount++;
                if ($weight <= -1.0) $strongBearish++;
            }
        }
        
        $bonus = 0.0;
        
        // Strong confluence bonus (5+ factors in same direction with 2+ strong)
        if ($bullishCount >= 5 && $strongBullish >= 2 && $bearishCount <= 1) {
            $bonus = 0.8;
        } elseif ($bearishCount >= 5 && $strongBearish >= 2 && $bullishCount <= 1) {
            $bonus = -0.8;
        }
        // Medium confluence (4+ factors with 1+ strong)
        elseif ($bullishCount >= 4 && $strongBullish >= 1 && $bearishCount <= 2) {
            $bonus = 0.4;
        } elseif ($bearishCount >= 4 && $strongBearish >= 1 && $bullishCount <= 2) {
            $bonus = -0.4;
        }
        
        return $bonus;
    }

    protected function determineSignal(float $score, ?float $volatility = null, array $features = []): string
    {
        // Stricter thresholds for higher winrate
        $buyThreshold = 1.8;
        $sellThreshold = -1.8;
        
        // In high volatility regime, require stronger conviction
        if ($volatility !== null && $volatility > 5) {
            $buyThreshold = 2.2;
            $sellThreshold = -2.2;
        }
        
        // In ranging market, be more conservative
        $regime = data_get($features, 'momentum.regime');
        if ($regime === 'RANGE' || $regime === 'HIGH VOL CHOP') {
            $buyThreshold = 2.0;
            $sellThreshold = -2.0;
        }
        
        if ($score >= $buyThreshold) {
            return 'BUY';
        }

        if ($score <= $sellThreshold) {
            return 'SELL';
        }

        return 'NEUTRAL';
    }

    protected function contribute(
        bool $condition,
        float $weight,
        string $reason,
        float &$score,
        array &$reasons,
        array &$factors,
        array $context = []
    ): void {
        if (!$condition) {
            return;
        }

        $score += $weight;
        $reasons[] = $reason;
        $factors[] = [
            'reason' => $reason,
            'weight' => $weight,
            'context' => $context,
        ];
    }

    protected function formatFloat(?float $value): string
    {
        return $value === null ? 'n/a' : number_format($value, 2);
    }

    protected function buildQualitySummary(float $score, array $features, ?float $volatility): array
    {
        $qualityScore = (float) (data_get($features, 'health.completeness') ?? 0.6);
        $flags = [];

        if (data_get($features, 'health.is_degraded')) {
            $qualityScore -= 0.2;
            $flags[] = [
                'code' => 'data_gaps',
                'label' => 'Data inti belum lengkap',
                'severity' => 'danger',
            ];
        }

        if (data_get($features, 'long_short.is_stale')) {
            $qualityScore -= 0.1;
            $flags[] = [
                'code' => 'longshort_stale',
                'label' => 'Long/short ratio >6 jam',
                'severity' => 'warning',
            ];
        }

        if ($volatility !== null && $volatility > 6 && abs($score) < 1.5) {
            $qualityScore -= 0.1;
            $flags[] = [
                'code' => 'volatility_chop',
                'label' => 'Volatilitas tinggi vs edge rendah',
                'severity' => 'warning',
            ];
        }

        if (data_get($features, 'momentum.regime') === 'HIGH VOL CHOP') {
            $qualityScore -= 0.05;
            $flags[] = [
                'code' => 'regime_chop',
                'label' => 'Regime chop terdeteksi',
                'severity' => 'info',
            ];
        }

        $qualityScore = max(0.1, min(1.0, $qualityScore));

        $status = match (true) {
            $qualityScore >= 0.8 => 'HIGH',
            $qualityScore >= 0.55 => 'MEDIUM',
            default => 'LOW',
        };

        return [
            'score' => round($qualityScore, 2),
            'status' => $status,
            'flags' => $flags,
        ];
    }
}
