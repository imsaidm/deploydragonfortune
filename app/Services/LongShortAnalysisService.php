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
}
