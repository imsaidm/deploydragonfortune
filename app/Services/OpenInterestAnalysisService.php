<?php

namespace App\Services;

/**
 * AI Risk Analysis Service for Open Interest
 * 
 * Framework Analisis:
 * 1. OI Trend - Rising vs Falling
 * 2. Price Correlation - OI vs Price Divergence/Convergence
 * 3. Volatility Proxy - Rate of Change in OI
 * 4. Risk Assessment - Squeeze Potential
 */
class OpenInterestAnalysisService
{
    /**
     * Analyze market condition based on Open Interest and Price data
     */
    public function analyzeMarketCondition(array $currentData, ?array $historyData = null): array
    {
        if (empty($currentData)) {
            return $this->emptyAnalysis();
        }

        // Metrics Calculation
        $stats = $this->calculateStatistics($currentData);
        
        // Trend Analysis
        $trend = $this->analyzeTrend($historyData);
        
        // Correlation Analysis
        $correlation = $this->analyzeCorrelation($historyData);
        
        // Risk Assessment
        $risks = $this->assessRisks($stats, $trend, $correlation);
        
        // Generate Insights
        $insights = $this->generateInsights($stats, $trend, $correlation, $risks);
        
        return [
            'market_status' => $this->determineMarketStatus($risks, $trend),
            'trend_direction' => $trend['direction'] ?? 'Neutral',
            'sentiment' => $correlation['sentiment'] ?? 'Neutral',
            'primary_risk' => $risks['primary'] ?? 'None',
            'reasons' => $insights,
            'metrics' => [
                'total_oi' => $stats['total_oi'],
                'avg_oi' => $stats['avg_oi'],
                'oi_change_24h' => $trend['change_24h'] ?? 0,
                'oi_change_percent' => $trend['change_percent'] ?? 0,
            ]
        ];
    }

    private function calculateStatistics(array $data): array
    {
        $oiValues = array_column($data, 'open_interest');
        $totalOi = array_sum($oiValues);
        $count = count($oiValues);
        
        return [
            'total_oi' => $totalOi,
            'avg_oi' => $count > 0 ? $totalOi / $count : 0,
            'max_oi' => $count > 0 ? max($oiValues) : 0,
            'count' => $count
        ];
    }

    private function analyzeTrend(?array $historyData): array
    {
        if (empty($historyData)) {
            return ['direction' => 'Unknown', 'change_24h' => 0, 'change_percent' => 0];
        }

        // Sort by time descending
        usort($historyData, fn($a, $b) => $b['time'] - $a['time']);
        
        $current = $historyData[0]['open_interest'] ?? 0;
        
        // Find ~24h ago
        $h24Ago = $historyData[0]['time'] - (24 * 3600 * 1000); // approx
        $prev = null;
        
        foreach ($historyData as $row) {
            if ($row['time'] <= $h24Ago) {
                $prev = $row['open_interest'];
                break;
            }
        }
        
        // If not found, use oldest
        if ($prev === null && count($historyData) > 0) {
            $prev = end($historyData)['open_interest'];
        }
        
        if (!$prev) return ['direction' => 'Unknown', 'change_24h' => 0, 'change_percent' => 0];

        $change = $current - $prev;
        $pctChange = ($change / $prev) * 100;
        
        $direction = 'Neutral';
        if ($pctChange > 5) $direction = 'Rising Rapidly';
        elseif ($pctChange > 1) $direction = 'Rising';
        elseif ($pctChange < -5) $direction = 'Falling Rapidly';
        elseif ($pctChange < -1) $direction = 'Falling';

        return [
            'direction' => $direction,
            'change_24h' => $change,
            'change_percent' => $pctChange
        ];
    }

    private function analyzeCorrelation(?array $historyData): array
    {
        if (empty($historyData) || count($historyData) < 2) {
            return ['sentiment' => 'Unknown', 'regime' => 'Unknown'];
        }
        
        // Simple correlation check on last 24h
        // Price Up + OI Up = Bullish (New money coming in)
        // Price Up + OI Down = Weakening Trend (Short covering)
        // Price Down + OI Up = Bearish (Aggressive shorting)
        // Price Down + OI Down = Weakening Bear (Long liquidation)
        
        $current = $historyData[0];
        $start = end($historyData);
        
        $priceChange = ($current['price'] ?? 0) - ($start['price'] ?? 0);
        $oiChange = ($current['open_interest'] ?? 0) - ($start['open_interest'] ?? 0);
        
        $sentiment = 'Neutral';
        $regime = 'Consolidation';
        
        if ($priceChange > 0) {
            if ($oiChange > 0) {
                $sentiment = 'Bullish';
                $regime = 'Strong Trend';
            } else {
                $sentiment = 'Cautious';
                $regime = 'Short Covering / Weakening';
            }
        } else {
            if ($oiChange > 0) {
                $sentiment = 'Bearish';
                $regime = 'Aggressive Shorting';
            } else {
                $sentiment = 'Cautious';
                $regime = 'Long Liquidation / Weakening';
            }
        }
        
        return [
            'sentiment' => $sentiment,
            'regime' => $regime,
            'price_change_dir' => $priceChange > 0 ? 'Up' : 'Down',
            'oi_change_dir' => $oiChange > 0 ? 'Up' : 'Down'
        ];
    }

    private function assessRisks(array $stats, array $trend, array $correlation): array
    {
        $risks = [];
        
        if ($trend['direction'] === 'Rising Rapidly') {
            $risks[] = 'Leverage Overheating';
        }
        
        if ($correlation['regime'] === 'Aggressive Shorting') {
            $risks[] = 'Potential Short Squeeze';
        }
        
        if ($correlation['regime'] === 'Strong Trend' && abs($trend['change_percent']) > 10) {
            $risks[] = 'FOMO / Chase Risk';
        }
        
        $primary = empty($risks) ? 'Low' : $risks[0];
        
        return [
            'primary' => $primary,
            'all' => $risks
        ];
    }

    private function generateInsights(array $stats, array $trend, array $correlation, array $risks): array
    {
        $insights = [];
        
        // Insight 1: Trend
        $dirIcon = str_contains($trend['direction'], 'Rising') ? '📈' : (str_contains($trend['direction'], 'Falling') ? '📉' : '➡️');
        $insights[] = sprintf(
            "%s OI is %s (%+.2f%% in 24h). Total Open Interest currently at %.2f.",
            $dirIcon, strtolower($trend['direction']), $trend['change_percent'], $stats['total_oi']
        );
        
        // Insight 2: Sentiment/Correlation
        $insights[] = sprintf(
            "MARKET REGIME: %s. Price is %s while OI is %s.",
            $correlation['regime'],
            strtolower($correlation['price_change_dir']),
            strtolower($correlation['oi_change_dir'])
        );
        
        // Insight 3: Risk
        if ($risks['primary'] !== 'Low') {
            $insights[] = sprintf(
                "⚠️ RISK ALERT: %s detected.",
                $risks['primary']
            );
        } else {
            $insights[] = "✅ Risk levels appear normal. No anomalies detected.";
        }
        
        return $insights;
    }
    
    private function determineMarketStatus(array $risks, array $trend): string
    {
        if (in_array('Leverage Overheating', $risks['all'])) return 'Overheated';
        if ($trend['direction'] === 'Rising Rapidly') return 'Heating Up';
        if ($trend['direction'] === 'Falling Rapidly') return 'Cooling Down';
        return 'Stable';
    }

    public function formatForDisplay(array $analysis): string
    {
         if (empty($analysis['reasons'])) return "No analysis available.";
         
         return implode("\n\n", $analysis['reasons']);
    }

    private function emptyAnalysis(): array
    {
        return [
            'market_status' => 'Unknown',
            'trend_direction' => 'Unknown',
            'sentiment' => 'Unknown',
            'primary_risk' => 'None',
            'reasons' => ['No data available for analysis.'],
            'metrics' => []
        ];
    }
}
