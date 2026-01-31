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
    public function analyze(string $symbol, string $range)
    {
        // 1. Fetch Latest Snapshot
        $heatmap = LiquidationHeatmap::where('symbol', $symbol)
            ->where('range', $range)
            ->latest('created_at')
            ->first();

        if (!$heatmap) {
            return [
                'success' => false,
                'message' => 'No data found for this configuration.'
            ];
        }

        // 2. Fetch Relationships
        // 2. Fetch Components (Optimized for Memory)
        // Use toBase() to skip Model hydration and save memory
        $yAxis = $heatmap->yAxis()->toBase()->select('price_level', 'sequence_order')->get();
        $candlesticks = $heatmap->candlesticks()->toBase()->select('timestamp', 'sequence_order', 'open_price', 'high_price', 'low_price', 'close_price')->get();
        
        // Fetch leverage data using a cursor or chunking if possible, but for now toBase() is a big step up
        // We only need specific columns
        $leverageData = $heatmap->leverageData()
            ->toBase()
            ->select('x_position', 'y_position', 'liquidation_amount')
            ->get();

        // 3. Process Data
        // Map sequence_order to price_level (Y-Axis)
        // If toBase returns stdClass objects, pluck might need adjustments or we loop manually. 
        // Safer to loop manually for raw DB results.
        $yMap = [];
        foreach ($yAxis as $row) {
            $yMap[$row->sequence_order] = $row->price_level;
        }

        // Map x_position (sequence_order) to timestamp (X-Axis)
        $xMap = [];
        $candleData = [];
        foreach ($candlesticks as $candle) {
            $xMap[$candle->sequence_order] = $candle->timestamp;
            
            $candleData[] = [
                'x' => $candle->timestamp * 1000,
                'o' => $candle->open_price,
                'h' => $candle->high_price,
                'l' => $candle->low_price,
                'c' => $candle->close_price
            ];
        }
        
        $chartData = [];
        // Aggregation for Insights
        $priceLevelsParams = []; // [price => total_liquidation]

        foreach ($leverageData as $point) {
            $price = $yMap[$point->y_position] ?? 0;
            $timestamp = $xMap[$point->x_position] ?? null;
            
            if ($price == 0 || !$timestamp) continue;

            $chartData[] = [
                'x' => $timestamp * 1000,
                'y' => $price,
                'v' => $point->liquidation_amount
            ];

            // Sum up liquidation "fuel" per price level
            if (!isset($priceLevelsParams[$price])) {
                $priceLevelsParams[$price] = 0;
            }
            $priceLevelsParams[$price] += $point->liquidation_amount;
        }

        // 4. Transform Candles for Overlay
        $priceLine = collect($candleData)->map(function ($c) {
            return [
                'x' => $c['x'],
                'y' => $c['y'] ?? $c['c'] // Fallback if y is not mapped
            ];
        });

        // 5. Generate Insights (Magnets & Fuel)
        $lastCandle = collect($candleData)->last();
        $currentPrice = $lastCandle['c'] ?? 0;
        $insights = $this->generateInsights($priceLevelsParams, $currentPrice);

        return [
            'success' => true,
            'data' => [
                'heatmap' => $chartData,
                'price_line' => $priceLine,
                'current_price' => $currentPrice,
                'insights' => $insights
            ]
        ];
    }

    /**
     * Enhanced Logic:
     * 1. Magnet: Top price levels with highest visibility.
     * 2. Bias: Long vs Short Liquidity distribution.
     * 3. Walls: Top 10 strongest clusters.
     */
    private function generateInsights(array $priceLevels, float $currentPrice)
    {
        ksort($priceLevels);
        
        $maxLiquidation = 0;
        $magnetPrice = 0;
        
        $longFuel = 0; // Below current price
        $shortFuel = 0; // Above current price
        $totalFuel = 0;
        $activeRadius = $currentPrice * 0.05; // 5% range for "Nearby" fuel

        $clusters = [];

        foreach ($priceLevels as $price => $volume) {
            $totalFuel += $volume;
            
            // Bias Calculation
            if ($price < $currentPrice) {
                $longFuel += $volume;
            } else {
                $shortFuel += $volume;
            }

            // Global Maxim (Strongest Magnet)
            if ($volume > $maxLiquidation) {
                $maxLiquidation = $volume;
                $magnetPrice = $price;
            }

            // Collect for Walls sorting
            $clusters[] = [
                'price' => (float)$price,
                'volume' => $volume,
                'distance_pct' => $currentPrice > 0 ? (($price - $currentPrice) / $currentPrice) * 100 : 0,
                'type' => $price < $currentPrice ? 'Long' : 'Short'
            ];
        }

        // Sort clusters by volume to find "Major Walls"
        usort($clusters, fn($a, $b) => $b['volume'] <=> $a['volume']);
        $majorWalls = array_slice($clusters, 0, 10);

        // Generate Narratives
        if ($currentPrice <= 0 || empty($priceLevels)) {
            $text = "Insufficient data to generate advanced insights.";
            $sentiment = "Neutral";
        } else {
            $distance = (($magnetPrice - $currentPrice) / $currentPrice) * 100;
            $direction = $distance > 0 ? 'above' : 'below';
            $sentiment = abs($distance) < 1.5 ? 'CRITICAL' : (abs($distance) < 5 ? 'WATCHING' : 'STABLE');

            $text = "Market is heavily gravitated towards <strong>$" . number_format($magnetPrice) . "</strong> (" . number_format(abs($distance), 2) . "% {$direction}). ";
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
            'major_walls' => $majorWalls,
            'text' => $text,
            'sentiment' => $sentiment
        ];
    }

    private function formatNumber($num)
    {
        if ($num >= 1000000000) return round($num / 1000000000, 2) . 'B';
        if ($num >= 1000000) return round($num / 1000000, 2) . 'M';
        if ($num >= 1000) return round($num / 1000, 2) . 'K';
        return round($num);
    }
}
