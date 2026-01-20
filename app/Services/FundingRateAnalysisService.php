<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AI Risk Analysis Service untuk Funding Rate
 * 
 * Framework Analisis 5 Dimensi:
 * 1. Funding Level - Normal vs Ekstrem
 * 2. Funding Breadth - Konsensus vs Fragmentasi
 * 3. Funding Momentum - Arah perubahan leverage
 * 4. Funding Volatility - Stabilitas vs Stress
 * 5. Risk Interpretation - Sehat/Panas/Rapuh
 * 
 * ATURAN KETAT:
 * - TIDAK mengarang data numerik
 * - TIDAK memprediksi arah harga
 * - TIDAK memberikan sinyal buy/sell
 * - HANYA interpretasi risiko berdasarkan data
 */
class FundingRateAnalysisService
{
    /**
     * Analisis komprehensif multi-dimensi dari funding rate
     */
    public function analyzeMarketCondition(array $exchangeData, ?array $historyData = null): array
    {
        if (empty($exchangeData)) {
            return $this->emptyAnalysis();
        }

        // Extract funding rates
        $rates = array_column($exchangeData, 'funding_rate');
        
        // 1. Calculate statistical metrics (Level)
        $stats = $this->calculateStatistics($rates);
        
        // 2. Analyze market positioning (Breadth)
        $positioning = $this->analyzePositioning($rates, $exchangeData);
        
        // 3. Analyze dynamics/momentum
        $dynamics = $this->analyzeDynamics($historyData, $stats);
        
        // 4. Assess volatility
        $volatility = $this->assessVolatility($stats, $dynamics);
        
        // 5. Assess risk levels
        $risks = $this->assessRisks($stats, $positioning, $dynamics, $volatility, $exchangeData);
        
        // Determine market status
        $marketStatus = $this->determineMarketStatus($stats, $positioning, $risks, $volatility);
        
        // Determine leverage condition
        $leverageCondition = $this->determineLeverageCondition($stats, $positioning, $dynamics);
        
        // Get recommended risk stance
        $riskStance = $this->determineRiskStance($marketStatus, $risks, $leverageCondition);
        
        // Generate multi-dimensional insights
        $keyInsights = $this->generateKeyInsights($stats, $positioning, $dynamics, $volatility, $risks, $exchangeData);
        
        return [
            'market_status' => $marketStatus,
            'crowd_positioning' => $positioning['status'],
            'leverage_condition' => $leverageCondition,
            'primary_risk' => $risks['primary'],
            'risk_stance' => $riskStance,
            'reasons' => $keyInsights,
            'metrics' => [
                // Funding Summary
                'avg_funding' => $stats['mean'],
                'min_funding' => $stats['min'],
                'max_funding' => $stats['max'],
                'spread_bps' => round(($stats['max'] - $stats['min']) * 10000, 1),
                'exchange_count' => $stats['count'],
                
                // Distribution
                'positive_ratio' => round($positioning['positiveRatio'] * 100, 1),
                'negative_ratio' => round($positioning['negativeRatio'] * 100, 1),
                'extreme_positive_ratio' => round($positioning['extremePositiveRatio'] * 100, 1),
                'extreme_negative_ratio' => round($positioning['extremeNegativeRatio'] * 100, 1),
                
                // Dynamics
                'delta_8h' => $dynamics['delta_8h'] ?? null,
                'delta_24h' => $dynamics['delta_24h'] ?? null,
                'std_24h' => $dynamics['std_24h'] ?? null,
                'spike_count' => $dynamics['spike_count'] ?? null,
                
                // Volatility
                'volatility_level' => $volatility['level'],
            ],
            'detailed_analysis' => [
                'positioning_details' => $positioning,
                'risk_details' => $risks,
                'dynamics_details' => $dynamics,
                'volatility_details' => $volatility,
                'statistical_summary' => $stats,
            ],
        ];
    }

    /**
     * Calculate statistical metrics from funding rates
     */
    private function calculateStatistics(array $rates): array
    {
        $count = count($rates);
        $mean = array_sum($rates) / $count;
        
        sort($rates);
        $median = $count % 2 === 0 
            ? ($rates[$count / 2 - 1] + $rates[$count / 2]) / 2
            : $rates[floor($count / 2)];
        
        $variance = array_sum(array_map(fn($r) => ($r - $mean) ** 2, $rates)) / $count;
        $stdDev = sqrt($variance);
        
        return [
            'mean' => $mean,
            'median' => $median,
            'stdDev' => $stdDev,
            'max' => max($rates),
            'min' => min($rates),
            'range' => max($rates) - min($rates),
            'count' => $count,
        ];
    }

    /**
     * Analyze crowd positioning (Breadth analysis)
     */
    private function analyzePositioning(array $rates, array $exchangeData): array
    {
        $total = count($rates);
        $positiveCount = count(array_filter($rates, fn($r) => $r > 0));
        $negativeCount = count(array_filter($rates, fn($r) => $r < 0));
        $neutralCount = count(array_filter($rates, fn($r) => $r == 0));
        
        $positiveRatio = $positiveCount / $total;
        $negativeRatio = $negativeCount / $total;
        
        // Find extremes (>0.05% or <-0.05%)
        $extremeThreshold = 0.0005;
        $extremePositive = array_filter($exchangeData, fn($ex) => $ex['funding_rate'] > $extremeThreshold);
        $extremeNegative = array_filter($exchangeData, fn($ex) => $ex['funding_rate'] < -$extremeThreshold);
        
        $extremePositiveRatio = count($extremePositive) / $total;
        $extremeNegativeRatio = count($extremeNegative) / $total;
        
        // Determine positioning status
        if ($positiveRatio > 0.80) {
            $status = 'Long Crowded';
            $severity = 'high';
            $consensus = 'strong';
        } elseif ($positiveRatio > 0.65) {
            $status = 'Long Bias';
            $severity = 'medium';
            $consensus = 'moderate';
        } elseif ($negativeRatio > 0.80) {
            $status = 'Short Crowded';
            $severity = 'high';
            $consensus = 'strong';
        } elseif ($negativeRatio > 0.65) {
            $status = 'Short Bias';
            $severity = 'medium';
            $consensus = 'moderate';
        } else {
            $status = 'Seimbang';
            $severity = 'low';
            $consensus = 'fragmented';
        }
        
        return [
            'status' => $status,
            'severity' => $severity,
            'consensus' => $consensus,
            'positiveRatio' => $positiveRatio,
            'negativeRatio' => $negativeRatio,
            'positiveCount' => $positiveCount,
            'negativeCount' => $negativeCount,
            'neutralCount' => $neutralCount,
            'extremePositiveRatio' => $extremePositiveRatio,
            'extremeNegativeRatio' => $extremeNegativeRatio,
            'extremePositive' => array_values($extremePositive),
            'extremeNegative' => array_values($extremeNegative),
        ];
    }

    /**
     * Analyze funding dynamics/momentum
     */
    private function analyzeDynamics(?array $historyData, array $stats): array
    {
        // Default values when history not available
        $dynamics = [
            'delta_8h' => null,
            'delta_24h' => null,
            'std_24h' => null,
            'spike_count' => null,
            'momentum' => 'data tidak tersedia',
            'trend' => 'unknown',
        ];
        
        if (empty($historyData)) {
            return $dynamics;
        }
        
        // Sort by time descending
        usort($historyData, fn($a, $b) => ($b['time'] ?? 0) - ($a['time'] ?? 0));
        
        $now = time() * 1000;
        $h8Ago = $now - (8 * 3600 * 1000);
        $h24Ago = $now - (24 * 3600 * 1000);
        
        // Get historical rates
        $recent = array_filter($historyData, fn($h) => ($h['time'] ?? 0) >= $h8Ago);
        $last24h = array_filter($historyData, fn($h) => ($h['time'] ?? 0) >= $h24Ago);
        
        if (!empty($recent) && !empty($last24h)) {
            $recentRates = array_column($recent, 'close');
            $last24hRates = array_column($last24h, 'close');
            
            if (!empty($recentRates) && !empty($last24hRates)) {
                $currentAvg = $stats['mean'];
                $avg8hAgo = array_sum($recentRates) / count($recentRates);
                $avg24hAgo = array_sum($last24hRates) / count($last24hRates);
                
                $dynamics['delta_8h'] = $currentAvg - $avg8hAgo;
                $dynamics['delta_24h'] = $currentAvg - $avg24hAgo;
                
                // Calculate 24h volatility
                $mean24h = array_sum($last24hRates) / count($last24hRates);
                $variance24h = array_sum(array_map(fn($r) => ($r - $mean24h) ** 2, $last24hRates)) / count($last24hRates);
                $dynamics['std_24h'] = sqrt($variance24h);
                
                // Count spikes (>2 std from mean)
                $spikeThreshold = $mean24h + (2 * $dynamics['std_24h']);
                $dynamics['spike_count'] = count(array_filter($last24hRates, fn($r) => abs($r) > $spikeThreshold));
                
                // Determine momentum
                if ($dynamics['delta_8h'] > 0.0001) {
                    $dynamics['momentum'] = 'Leverage Meningkat';
                    $dynamics['trend'] = 'increasing';
                } elseif ($dynamics['delta_8h'] < -0.0001) {
                    $dynamics['momentum'] = 'Leverage Menurun';
                    $dynamics['trend'] = 'decreasing';
                } else {
                    $dynamics['momentum'] = 'Stagnan';
                    $dynamics['trend'] = 'stable';
                }
            }
        }
        
        return $dynamics;
    }

    /**
     * Assess funding volatility
     */
    private function assessVolatility(array $stats, array $dynamics): array
    {
        $volatility = [
            'level' => 'Normal',
            'stress' => false,
            'description' => 'Funding rate relatif stabil',
        ];
        
        // Check current spread volatility
        $spreadBps = ($stats['max'] - $stats['min']) * 10000;
        
        // Check cross-exchange std deviation
        $stdDevPct = $stats['stdDev'] * 100;
        
        if ($spreadBps > 200 || $stdDevPct > 0.05) {
            $volatility['level'] = 'Tinggi';
            $volatility['stress'] = true;
            $volatility['description'] = 'Market menunjukkan tanda-tanda stress';
        } elseif ($spreadBps > 100 || $stdDevPct > 0.03) {
            $volatility['level'] = 'Elevated';
            $volatility['stress'] = false;
            $volatility['description'] = 'Volatilitas di atas rata-rata';
        }
        
        // Check 24h dynamics if available
        if ($dynamics['std_24h'] !== null && $dynamics['std_24h'] > 0.0003) {
            $volatility['level'] = 'Tinggi';
            $volatility['stress'] = true;
            $volatility['description'] = 'Historical volatility tinggi dalam 24h terakhir';
        }
        
        return $volatility;
    }

    /**
     * Assess various risk factors
     */
    private function assessRisks(array $stats, array $positioning, array $dynamics, array $volatility, array $exchangeData): array
    {
        $risks = [];
        
        // Long Squeeze risk
        if ($stats['mean'] > 0.0007) {
            $risks[] = [
                'type' => 'Long Squeeze',
                'level' => 'high',
                'description' => 'Funding ekstrem tinggi meningkatkan risiko long liquidation cascade',
            ];
        }
        
        // Short Squeeze risk
        if ($stats['mean'] < -0.0005) {
            $risks[] = [
                'type' => 'Short Squeeze',
                'level' => 'high',
                'description' => 'Funding negatif ekstrem berpotensi memicu short squeeze',
            ];
        }
        
        // Volatility risk
        if ($volatility['stress']) {
            $risks[] = [
                'type' => 'High Volatility',
                'level' => 'medium',
                'description' => 'Volatilitas funding rate mengindikasikan ketidakpastian market',
            ];
        }
        
        // Fragmentation risk
        $spreadBps = ($stats['max'] - $stats['min']) * 10000;
        if ($spreadBps > 150) {
            $risks[] = [
                'type' => 'Market Fragmentation',
                'level' => 'medium',
                'description' => 'Spread lebar menandakan likuiditas tersegmentasi',
            ];
        }
        
        // Leverage buildup risk
        $extremeCount = count($positioning['extremePositive']) + count($positioning['extremeNegative']);
        if ($extremeCount > count($exchangeData) * 0.3) {
            $risks[] = [
                'type' => 'Excessive Leverage',
                'level' => 'high',
                'description' => 'Leverage buildup berbahaya terdeteksi di banyak exchange',
            ];
        }
        
        // Momentum risk
        if ($dynamics['trend'] === 'increasing' && $stats['mean'] > 0.0005) {
            $risks[] = [
                'type' => 'Accelerating Leverage',
                'level' => 'medium',
                'description' => 'Leverage masih bertambah saat sudah tinggi',
            ];
        }
        
        // Determine primary risk
        $primary = 'Risiko Moderat';
        if (!empty($risks)) {
            $highRisks = array_filter($risks, fn($r) => $r['level'] === 'high');
            if (!empty($highRisks)) {
                $primary = reset($highRisks)['type'];
            } else {
                $primary = $risks[0]['type'];
            }
        }
        
        return [
            'primary' => $primary,
            'risks' => $risks,
            'extremeCount' => $extremeCount,
            'totalCount' => count($exchangeData),
            'riskCount' => count($risks),
            'highRiskCount' => count(array_filter($risks, fn($r) => $r['level'] === 'high')),
        ];
    }

    /**
     * Determine overall market status
     */
    private function determineMarketStatus(array $stats, array $positioning, array $risks, array $volatility): string
    {
        // Tidak Sehat: extreme conditions
        if ($stats['mean'] > 0.001 || $stats['mean'] < -0.0007) {
            return 'Tidak Sehat';
        }
        
        if ($risks['highRiskCount'] >= 2) {
            return 'Tidak Sehat';
        }
        
        if ($volatility['stress'] && $positioning['severity'] === 'high') {
            return 'Tidak Sehat';
        }
        
        // Panas: elevated conditions
        if ($positioning['severity'] === 'high') {
            return 'Panas';
        }
        
        if ($risks['riskCount'] >= 2 || $volatility['level'] === 'Tinggi') {
            return 'Panas';
        }
        
        if ($risks['riskCount'] >= 1) {
            return 'Panas';
        }
        
        // Sehat: normal conditions
        return 'Sehat';
    }

    /**
     * Determine leverage condition
     */
    private function determineLeverageCondition(array $stats, array $positioning, array $dynamics): string
    {
        $avgFundingPct = abs($stats['mean']) * 100;
        $extremeRatio = $positioning['extremePositiveRatio'] + $positioning['extremeNegativeRatio'];
        
        // Berlebihan: very high leverage indicators
        if ($avgFundingPct > 0.07 || $extremeRatio > 0.4) {
            return 'Berlebihan';
        }
        
        // Meningkat: momentum shows increasing + above normal
        if ($dynamics['trend'] === 'increasing' && $avgFundingPct > 0.03) {
            return 'Meningkat';
        }
        
        // Meningkat: above normal but stable
        if ($avgFundingPct > 0.05) {
            return 'Meningkat';
        }
        
        // Rendah: normal levels
        return 'Rendah';
    }

    /**
     * Determine recommended risk stance
     */
    private function determineRiskStance(string $marketStatus, array $risks, string $leverageCondition): string
    {
        if ($marketStatus === 'Tidak Sehat') {
            return 'Defensif';
        }
        
        if ($leverageCondition === 'Berlebihan') {
            return 'Defensif';
        }
        
        if ($marketStatus === 'Panas') {
            if ($risks['highRiskCount'] > 0) {
                return 'Defensif';
            }
            return 'Netral';
        }
        
        if ($risks['riskCount'] === 0 && $leverageCondition === 'Rendah') {
            return 'Agresif';
        }
        
        return 'Netral';
    }

    /**
     * Generate multi-dimensional key insights
     */
    private function generateKeyInsights(array $stats, array $positioning, array $dynamics, array $volatility, array $risks, array $exchangeData): array
    {
        $insights = [];
        $avgFundingPct = $stats['mean'] * 100;
        $spreadBps = ($stats['max'] - $stats['min']) * 10000;
        
        // INSIGHT 1: Funding Level & Distribution
        $positivePct = round($positioning['positiveRatio'] * 100);
        $extremePct = round(($positioning['extremePositiveRatio'] + $positioning['extremeNegativeRatio']) * 100);
        
        if ($stats['mean'] > 0.0005) {
            $insights[] = sprintf(
                '[LEVEL] Funding %.4f%% (ekstrem tinggi) dengan %d%% exchange positif — crowd heavy long',
                $avgFundingPct,
                $positivePct
            );
        } elseif ($stats['mean'] < -0.0003) {
            $insights[] = sprintf(
                '[LEVEL] Funding negatif %.4f%% dengan %d%% exchange negatif — crowd bearish',
                $avgFundingPct,
                100 - $positivePct
            );
        } else {
            $insights[] = sprintf(
                '[LEVEL] Funding %.4f%% dalam range normal — positioning tidak ekstrem',
                $avgFundingPct
            );
        }
        
        // INSIGHT 2: Momentum & Dynamics
        if ($dynamics['momentum'] !== 'data tidak tersedia') {
            if ($dynamics['trend'] === 'increasing') {
                $insights[] = sprintf(
                    '[MOMENTUM] Leverage MENINGKAT — funding naik %.4f%% dalam 8h terakhir',
                    ($dynamics['delta_8h'] ?? 0) * 100
                );
            } elseif ($dynamics['trend'] === 'decreasing') {
                $insights[] = sprintf(
                    '[MOMENTUM] Leverage MENURUN — deleveraging sedang terjadi',
                    abs(($dynamics['delta_8h'] ?? 0) * 100)
                );
            } else {
                $insights[] = '[MOMENTUM] Funding stagnan — tidak ada perubahan leverage signifikan';
            }
        }
        
        // INSIGHT 3: Volatility & Stress
        if ($volatility['stress']) {
            $insights[] = sprintf(
                '[VOLATILITY] STRESS terdeteksi — spread %.0f bps, market fragmented',
                $spreadBps
            );
        } elseif ($volatility['level'] === 'Elevated') {
            $insights[] = sprintf(
                '[VOLATILITY] Elevated — spread %.0f bps di atas normal',
                $spreadBps
            );
        }
        
        // INSIGHT 4: Exchange Consensus/Breadth
        if ($positioning['consensus'] === 'strong' && $positioning['severity'] === 'high') {
            $insights[] = sprintf(
                '[BREADTH] Konsensus KUAT — %d dari %d exchange sepakat (%s)',
                max($positioning['positiveCount'], $positioning['negativeCount']),
                $stats['count'],
                $positioning['status']
            );
        } elseif ($positioning['consensus'] === 'fragmented') {
            $insights[] = sprintf(
                '[BREADTH] Market TERFRAGMENTASI — tidak ada konsensus jelas antar exchange'
            );
        }
        
        // INSIGHT 5: Risk Interpretation
        if ($risks['highRiskCount'] > 0) {
            $highRiskTypes = array_map(fn($r) => $r['type'], array_filter($risks['risks'], fn($r) => $r['level'] === 'high'));
            $insights[] = sprintf(
                '[RISK] %s — kondisi ini meningkatkan probabilitas liquidation cascade',
                implode(' + ', $highRiskTypes)
            );
        } elseif ($risks['riskCount'] > 0) {
            $insights[] = '[RISK] Risiko moderat — monitor closely tapi belum critical';
        } else {
            $insights[] = '[RISK] Tidak ada risiko signifikan terdeteksi — kondisi relatif aman';
        }
        
        return $insights;
    }

    /**
     * Return empty analysis when no data available
     */
    private function emptyAnalysis(): array
    {
        return [
            'market_status' => 'Unknown',
            'crowd_positioning' => 'Unknown',
            'leverage_condition' => 'Unknown',
            'primary_risk' => 'Data tidak tersedia',
            'risk_stance' => 'Netral',
            'reasons' => ['Data tidak tersedia untuk analisis'],
            'metrics' => [],
            'detailed_analysis' => [],
        ];
    }

    /**
     * Format analysis untuk output text
     */
    public function formatForDisplay(array $analysis): string
    {
        $output = "MARKET STATUS:\n";
        $output .= strtoupper($analysis['market_status']) . "\n\n";
        
        $output .= "CROWD POSITIONING:\n";
        $output .= strtoupper($analysis['crowd_positioning']) . "\n\n";
        
        $output .= "LEVERAGE CONDITION:\n";
        $output .= strtoupper($analysis['leverage_condition'] ?? 'UNKNOWN') . "\n\n";
        
        $output .= "PRIMARY RISK:\n";
        $output .= $analysis['primary_risk'] . "\n\n";
        
        $output .= "RISK STANCE:\n";
        $output .= strtoupper($analysis['risk_stance']) . "\n\n";

        $output .= "KEY INSIGHTS:\n";
        foreach ($analysis['reasons'] as $reason) {
            $output .= "- " . $reason . "\n";
        }
        
        return $output;
    }
}
