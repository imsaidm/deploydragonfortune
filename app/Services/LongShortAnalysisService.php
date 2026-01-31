<?php

namespace App\Services;

class LongShortAnalysisService
{
    /**
     * Analyze market sentiment based on Top Account and Global Account Long/Short Ratios.
     *
     * @param float $topAccountRatio
     * @param float $globalAccountRatio
     * @return array
     */
    public function analyzeSentiment(float $topAccountRatio, float $globalAccountRatio): array
    {
        $topSentiment = $this->getSentimentLabel($topAccountRatio);
        $globalSentiment = $this->getSentimentLabel($globalAccountRatio);

        // Determine overall market stance
        $signal = 'Neutral';
        $color = 'text-gray-500'; // Default gray

        if ($topSentiment === 'Bullish' && $globalSentiment === 'Bullish') {
            $signal = 'Strong Bullish';
            $color = 'text-green-500';
        } elseif ($topSentiment === 'Bearish' && $globalSentiment === 'Bearish') {
            $signal = 'Strong Bearish';
            $color = 'text-red-500';
        } elseif ($topSentiment === 'Bullish' && $globalSentiment === 'Bearish') {
            $signal = 'In Divergence (Smart Money Long)';
            $color = 'text-blue-500'; // Divergence often favors Top Accounts (Smart Money)
        } elseif ($topSentiment === 'Bearish' && $globalSentiment === 'Bullish') {
            $signal = 'In Divergence (Smart Money Short)';
            $color = 'text-orange-500';
        }

        return [
            'top_sentiment' => $topSentiment,
            'global_sentiment' => $globalSentiment,
            'signal' => $signal,
            'signal_color' => $color,
        ];
    }

    /**
     * Generate a human-readable insight string with specific data points.
     *
     * @param float $topRatio
     * @param float $globalRatio
     * @param float $topPrevRatio
     * @param string $symbol
     * @return string
     */
    public function generateInsightText(float $topRatio, float $globalRatio, float $topPrevRatio, string $symbol): string
    {
        $analysis = $this->analyzeSentiment($topRatio, $globalRatio);
        $delta = $topRatio - $topPrevRatio;
        $deltaPct = $topPrevRatio > 0 ? ($delta / $topPrevRatio) * 100 : 0;
        $deltaStr = $delta >= 0 ? "+" . number_format($deltaPct, 2) . "%" : number_format($deltaPct, 2) . "%";
        
        $insight = "";

        // Insight Logic V2: More professional, data-driven
        if ($analysis['signal'] === 'Strong Bullish') {
            $insight = "<strong>Bullish Confluence:</strong> Both Smart Money and Retail are Net Long. Top Trader ratio has shifted {$deltaStr} in the last interval, indicating high conviction accumulation.";
        } elseif ($analysis['signal'] === 'Strong Bearish') {
            $insight = "<strong>Bearish Confluence:</strong> Market participants are uniformly net short. Smart Money ratio moved {$deltaStr}, suggesting continued distribution.";
        } elseif (str_contains($analysis['signal'], 'Divergence (Smart Money Long)')) {
            $insight = "<strong>High Conviction Buy:</strong> Significant divergence detected. While the retail crowd is fearful (Ratio: " . number_format($globalRatio, 2) . "), Smart Money is aggressively buying dips (Ratio: " . number_format($topRatio, 2) . ", {$deltaStr}). This typically precedes a price reversal to the upside.";
        } elseif (str_contains($analysis['signal'], 'Divergence (Smart Money Short)')) {
            $insight = "<strong>Institutional Distribution:</strong> Warning signs active. Retail traders are over-leveraged Long, but Top/Smart accounts are Net Short (Ratio: " . number_format($topRatio, 2) . "). This smart money selling ({$deltaStr} shift) often signals a liquidity trap or upcoming correction.";
        } else {
            $insight = "<strong>Mixed Signals:</strong> No clear directional consensus. Smart Money positioning is neutral/choppy ({$deltaStr} change). Monitor for a breakout above 1.1 or breakdown below 0.9 ratio.";
        }

        return $insight;
    }

    private function getSentimentLabel(float $ratio): string
    {
        if ($ratio >= 1.1) {
            return 'Bullish';
        } elseif ($ratio <= 0.9) {
            return 'Bearish';
        }
        return 'Neutral';
    }

    /**
     * Calculate 24h High/Low extremes for Smart Money Ratio.
     */
    public function calculate24hExtremes($historyData): array
    {
        if ($historyData->isEmpty()) {
            return ['high' => 0, 'low' => 0, 'current_position_pct' => 50];
        }

        $ratios = $historyData->pluck('top_account_long_short_ratio');
        $max = $ratios->max();
        $min = $ratios->min();
        $current = $ratios->first(); // Assuming latest is first

        $range = $max - $min;
        // Position percentage (0% = at Low, 100% = at High)
        $positionPct = ($range > 0) ? (($current - $min) / $range) * 100 : 50;

        return [
            'high' => $max,
            'low' => $min,
            'current_position_pct' => round($positionPct, 1)
        ];
    }

    /**
     * Detect specific contrarian usage signals.
     * Logic: Smart Money Bullish (>1.1) AND Retail Bearish (<0.9) Or Vice Versa.
     */
    public function detectContrarianSignal(float $topRatio, float $globalRatio): array
    {
        $hasSignal = false;
        $type = null;
        $description = null;

        if ($topRatio >= 1.2 && $globalRatio <= 0.9) {
            $hasSignal = true;
            $type = 'STRONG_BUY';
            $description = 'Smart Money Aggressively Long vs Retail Short';
        } elseif ($topRatio <= 0.8 && $globalRatio >= 1.1) {
             $hasSignal = true;
             $type = 'STRONG_SELL';
             $description = 'Smart Money Dumping vs Retail FOMO';
        }

        return [
            'has_signal' => $hasSignal,
            'type' => $type,
            'description' => $description
        ];
    }

    /**
     * Calculate Conviction Score (0-100) based on Net Long/Short Exposure.
     * Higher score = Higher conviction in current direction.
     */
    public function calculateConvictionScore(float $longPct, float $shortPct): array
    {
        // Net absolute difference
        $netDiff = abs($longPct - $shortPct);
        
        // Map 0-100% diff to a score. 
        // Typically diff > 40% is extreme (e.g. 70/30).
        // Let's normalize: 0% diff = 0 score, 50% diff = 100 score.
        $score = min(($netDiff / 50) * 100, 100);
        
        $direction = ($longPct > $shortPct) ? 'Long' : 'Short';
        
        $label = 'Weak';
        if ($score > 70) $label = 'Strong';
        elseif ($score > 40) $label = 'Moderate';

        return [
            'score' => round($score),
            'direction' => $direction,
            'label' => $label
        ];
    }

    /**
     * Calculate where the current value sits in the historical distribution (Percentile).
     */
    public function calculatePercentile(float $currentValue, $historyValues): float
    {
        if ($historyValues->isEmpty()) return 50.0;

        // Ensure all values are floats and sort ascending
        $sorted = $historyValues->map(fn($v) => (float)$v)->sort()->values();
        $count = $sorted->count();
        
        // Find how many items are smaller than current
        $rank = $sorted->filter(fn($v) => $v < $currentValue)->count();

        // Percentile = (Rank / Total) * 100
        // If rank is 0 (smallest), percentile 0.
        // If rank is count-1 (largest), percentile near 100.
        
        return round(($rank / $count) * 100, 1);
    }

    /**
     * Calculate consecutive trend periods (Streak).
     */
    public function calculateTrendStreak($historyData): array
    {
         if ($historyData->isEmpty()) return ['count' => 0, 'direction' => 'Neutral'];

         $first = $historyData->first();
         $currentDirection = $this->getSentimentLabel($first->top_account_long_short_ratio);
         
         if ($currentDirection === 'Neutral') return ['count' => 0, 'direction' => 'Neutral'];

         $streak = 0;
         foreach ($historyData as $item) {
             $direction = $this->getSentimentLabel($item->top_account_long_short_ratio);
             if ($direction === $currentDirection) {
                 $streak++;
             } else {
                 break;
             }
         }

         return [
             'count' => $streak,
             'direction' => $currentDirection
         ];
    }

    /**
     * Calculate Sentiment Impact (Divergence Gap Intensity).
     */
    public function calculateSentimentImpact(float $topRatio, float $globalRatio): array
    {
        $gap = abs($topRatio - $globalRatio);
        
        // Intensity 0-100. Gap of 1.0 is very high (e.g. 2.0 vs 1.0)
        $intensity = min(($gap / 1.0) * 100, 100);
        
        $label = 'Normal';
        if ($intensity > 80) $label = 'Extreme';
        elseif ($intensity > 50) $label = 'High';
        elseif ($intensity > 20) $label = 'Rising';

        return [
            'gap' => round($gap, 2),
            'intensity' => round($intensity),
            'label' => $label
        ];
    }

    /**
     * Calculate Sentiment Volatility (Rate of change in Top Ratio).
     */
    public function calculateSentimentVolatility($historyData): array
    {
        if ($historyData->count() < 5) return ['score' => 0, 'label' => 'Stable'];

        $ratios = $historyData->pluck('top_account_long_short_ratio')->toArray();
        $diffs = [];
        for ($i = 0; $i < count($ratios) - 1; $i++) {
            $diffs[] = abs($ratios[$i] - $ratios[$i+1]);
        }
        
        $avgChange = array_sum($diffs) / count($diffs);
        // Normalize: 0.1 avg change per candle is high.
        $score = min(($avgChange / 0.1) * 100, 100);

        $label = 'Stable';
        if ($score > 70) $label = 'Erratic';
        elseif ($score > 40) $label = 'Active';

        return [
            'score' => round($score),
            'label' => $label
        ];
    }

    /**
     * Calculate Gap Momentum (Widening vs Narrowing).
     */
    public function calculateGapMomentum(float $currTop, float $currGlobal, float $prevTop, float $prevGlobal): array
    {
        $currGap = abs($currTop - $currGlobal);
        $prevGap = abs($prevTop - $prevGlobal);
        $change = $currGap - $prevGap;

        $trend = 'Stable';
        if ($change > 0.05) $trend = 'Widening';
        elseif ($change < -0.05) $trend = 'Narrowing';

        return [
            'value' => round($currGap, 2),
            'change' => round($change, 2),
            'trend' => $trend
        ];
    }

    /**
     * Determine dominance based on 4h net change.
     */
    public function calculateDominance($historyData): array
    {
        if ($historyData->count() < 4) return ['actor' => 'Neutral', 'intensity' => 'Low'];

        $latest = $historyData->first();
        $fourH = $historyData->skip(3)->first() ?? $historyData->last();

        $topChange = abs($latest->top_account_long_short_ratio - $fourH->top_account_long_short_ratio);
        
        // This is a simplified dominance check focusing on volatility of Smart Money
        $intensity = 'Stable';
        if ($topChange > 0.3) $intensity = 'Aggressive';
        elseif ($topChange > 0.1) $intensity = 'Active';

        return [
            'intensity' => $intensity,
            'change' => round($topChange, 2)
        ];
    }
}
