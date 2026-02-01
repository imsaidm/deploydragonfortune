<?php

namespace App\Services;

use App\Models\LiquidationHeatmap;
use Illuminate\Support\Collection;

class LiquidationHeatmapAnalysisService
{
    /**
     * Process heatmap data for a specific symbol and timeframe.
     * Returns chart data and analysis insights.
     */
    /**
     * Get a summary of all ranges for a symbol.
     * Used for the multi-range insight cards.
     */
    public function getSummaryForSymbol(string $symbol)
    {
        $heatmaps = LiquidationHeatmap::where('symbol', $symbol)
            ->latest('created_at')
            ->get()
            ->unique('range'); // Only latest per range

        $summary = [];
        foreach ($heatmaps as $heatmap) {
            $summary[$heatmap->range] = $this->analyzeHeatmap($heatmap, true);
        }

        return [
            'success' => true,
            'symbol' => $symbol,
            'summary' => $summary
        ];
    }

    /**
     * Internal helper to process a heatmap model instance.
     * $summaryOnly skips full chart data mapping for better performance.
     */
    private function analyzeHeatmap(LiquidationHeatmap $heatmap, bool $summaryOnly = false)
    {
        try {
            // 1. Fetch Components (Optimized & Sorted)
            $yAxis = $heatmap->yAxis()->orderBy('sequence_order')->toBase()->select('price_level', 'sequence_order')->get();
            $candlesticks = $heatmap->candlesticks()->orderBy('sequence_order')->toBase()->select('timestamp', 'sequence_order', 'close_price')->get();
            $leverageData = $heatmap->leverageData()->toBase()->select('y_position', 'liquidation_amount')->get();

            if ($yAxis->isEmpty() || $candlesticks->isEmpty()) {
                 \Illuminate\Support\Facades\Log::warning("Heatmap ID {$heatmap->id} [{$heatmap->symbol} {$heatmap->range}] has missing components. Y: ".count($yAxis).", Candles: ".count($candlesticks));
                 
                 // Return empty data instead of crashing
                 return $this->getEmptyHeatmapData();
            }

            // 2. Map Y-Axis for Insight summing
            $yMap = [];
            foreach ($yAxis as $row) {
                $yMap[(int)$row->sequence_order] = (float)$row->price_level;
            }

            // 3. Current Price from last candle
            $currentPrice = (float)($candlesticks->last()?->close_price ?? 0);

            // 4. Sum up liquidation fuel per price level
            $priceLevelsParams = [];
            foreach ($leverageData as $point) {
                $price = $yMap[(int)$point->y_position] ?? null;
                if ($price === null) continue;

                $priceKey = (string)$price;
                if (!isset($priceLevelsParams[$priceKey])) {
                    $priceLevelsParams[$priceKey] = 0;
                }
                $priceLevelsParams[$priceKey] += (float)$point->liquidation_amount;
            }

            // 5. Generate Insights (Numeric Sort)
            ksort($priceLevelsParams, SORT_NUMERIC);
            $insights = $this->generateInsights($priceLevelsParams, $currentPrice);

            if ($summaryOnly) {
                return [
                    'sentiment' => $insights['sentiment'],
                    'magnet_price' => $insights['magnet_price'],
                    'magnet_strength' => $insights['magnet_strength'],
                    'total_fuel' => $insights['total_fuel'],
                    'has_data' => !empty($priceLevelsParams)
                ];
            }

            // 6. Full Analysis (Price Line sorting)
            $candlesticksFull = $heatmap->candlesticks()->orderBy('sequence_order')->toBase()->select('timestamp', 'sequence_order', 'open_price', 'high_price', 'low_price', 'close_price')->get();
            $leverageDataFull = $heatmap->leverageData()->toBase()->select('x_position', 'y_position', 'liquidation_amount')->get();
            
            $xMap = [];
            $candleData = [];
            $seenTimestamps = [];
            foreach ($candlesticksFull as $candle) {
                $ts = (int)$candle->timestamp;
                if (isset($seenTimestamps[$ts])) continue;
                $seenTimestamps[$ts] = true;

                $xMap[(int)$candle->sequence_order] = $ts;
                $candleData[] = [
                    'x' => $ts * 1000,
                    'o' => $candle->open_price,
                    'h' => $candle->high_price,
                    'l' => $candle->low_price,
                    'c' => (float)$candle->close_price
                ];
            }

            $chartData = [];
            foreach ($leverageDataFull as $point) {
                $price = $yMap[(int)$point->y_position] ?? 0;
                $timestamp = $xMap[(int)$point->x_position] ?? null;
                if ($price == 0 || !$timestamp) continue;

                $chartData[] = [
                    'x' => $timestamp * 1000,
                    'y' => (float)$price,
                    'v' => (float)$point->liquidation_amount
                ];
            }

            return [
                'heatmap' => $chartData,
                'price_line' => $candleData,
                'current_price' => $currentPrice,
                'insights' => $insights
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("analyzeHeatmap CRASHED for Heatmap ID {$heatmap->id}: " . $e->getMessage(), [
                'symbol' => $heatmap->symbol,
                'range' => $heatmap->range,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return safe empty data instead of crashing
            return $this->getEmptyHeatmapData($summaryOnly);
        }
    }

    private function getEmptyHeatmapData($summaryOnly = false)
    {
        $baseData = [
            'sentiment' => 'STABLE',
            'magnet_price' => 0,
            'magnet_strength' => 0,
            'total_fuel' => 0,
            'has_data' => false
        ];

        if ($summaryOnly) {
            return $baseData;
        }

        return [
            'heatmap' => [],
            'price_line' => [],
            'current_price' => 0,
            'insights' => [
                'magnet_price' => 0,
                'magnet_strength' => 0,
                'long_fuel' => 0,
                'short_fuel' => 0,
                'total_fuel' => 0,
                'bias' => ['long_pct' => 50, 'short_pct' => 50],
                'major_walls' => [],
                'text' => "Data error. Please contact support.",
                'sentiment' => 'STABLE'
            ]
        ];
    }

    public function analyze(string $symbol, string $range)
    {
        $heatmap = LiquidationHeatmap::where('symbol', $symbol)
            ->where('range', $range)
            ->latest('id')
            ->first();

        if (!$heatmap) {
            return ['success' => false, 'message' => 'No data found.'];
        }

        $data = $this->analyzeHeatmap($heatmap);

        return [
            'success' => true,
            'data' => $data
        ];
    }

    private function generateInsights(array $priceLevels, float $currentPrice)
    {
        $maxLiquidation = 0;
        $magnetPrice = 0;
        
        $longFuel = 0;
        $shortFuel = 0;
        $totalFuel = 0;
        $clusters = [];

        if (empty($priceLevels)) {
            return [
                'magnet_price' => 0,
                'magnet_strength' => 0,
                'long_fuel' => 0,
                'short_fuel' => 0,
                'total_fuel' => 0,
                'bias' => ['long_pct' => 50, 'short_pct' => 50],
                'major_walls' => [],
                'text' => "No liquidation data found for this timeframe.",
                'sentiment' => 'STABLE'
            ];
        }

        foreach ($priceLevels as $pKey => $volume) {
            $price = (float)$pKey;
            $totalFuel += $volume;
            if ($price < $currentPrice) {
                $longFuel += $volume;
            } else {
                $shortFuel += $volume;
            }

            if ($volume > $maxLiquidation) {
                $maxLiquidation = $volume;
                $magnetPrice = $price;
            }

            $clusters[] = [
                'price' => $price,
                'volume' => $volume,
                'distance_pct' => $currentPrice > 0 ? (($price - $currentPrice) / $currentPrice) * 100 : 0,
                'type' => $price < $currentPrice ? 'Long' : 'Short'
            ];
        }

        usort($clusters, fn($a, $b) => $b['volume'] <=> $a['volume']);
        $majorWalls = array_slice($clusters, 0, 10);

        if ($currentPrice <= 0 || empty($priceLevels)) {
            $text = "Insufficient data to generate advanced insights.";
            $sentiment = "Neutral";
            $magnetPrice = $magnetPrice ?: 0;
            $distance = 0;
        } else {
            $distance = (($magnetPrice - $currentPrice) / $currentPrice) * 100;
            $direction = $distance > 0 ? 'above' : 'below';
            $sentiment = abs($distance) < 0.8 ? 'CRITICAL' : (abs($distance) < 3.5 ? 'WATCHING' : 'STABLE');

            $text = "Market is heavily gravitated towards <strong>$" . $this->formatPrice($magnetPrice) . "</strong> (" . number_format(abs($distance), 2) . "% {$direction}). ";
            $text .= "Total liquidation fuel detected: <strong>$" . $this->formatNumber($totalFuel) . "</strong>.";
        }

        return [
            'magnet_price' => $magnetPrice,
            'magnet_strength' => $maxLiquidation,
            'long_fuel' => $longFuel,
            'short_fuel' => $shortFuel,
            'total_fuel' => $totalFuel,
            'bias' => $totalFuel > 0 ? [
                'long_pct' => round(($longFuel / $totalFuel) * 100, 1),
                'short_pct' => round(($shortFuel / $totalFuel) * 100, 1)
            ] : ['long_pct' => 50, 'short_pct' => 50],
            'major_walls' => array_values($majorWalls),
            'text' => $text,
            'sentiment' => $sentiment
        ];
    }

    private function formatPrice($price)
    {
        if ($price == 0) return '0.00';
        if ($price < 1) return number_format($price, 4);
        if ($price < 100) return number_format($price, 2);
        return number_format($price, 0);
    }

    private function formatNumber($num)
    {
        if ($num >= 1000000000) return round($num / 1000000000, 2) . 'B';
        if ($num >= 1000000) return round($num / 1000000, 2) . 'M';
        if ($num >= 1000) return round($num / 1000, 2) . 'K';
        return round($num, 2);
    }
}
