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
        // We use Close price for the line overlay
        $priceLine = collect($candleData)->map(function ($c) {
            return [
                'x' => $c['x'],
                'y' => $c['c']
            ];
        });

        // 5. Generate Insights (Magnets & Fuel)
        $currentPrice = $candles->last()->close_price ?? 0;
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
     * Logic:
     * 1. Magnet: The price level with the HIGHEST visible cumulative leverage.
     * 2. Fuel: Total liquidation amount in the immediate vicinity (+/- 5%) of current price.
     */
    private function generateInsights(array $priceLevels, float $currentPrice)
    {
        // Sort levels by price to find nearest
        ksort($priceLevels);
        
        // Find Global Maxima (Strongest Magnet)
        $maxLiquidation = 0;
        $magnetPrice = 0;
        
        // Find Local Fuel (Near Price)
        $fuel = 0;
        $range = $currentPrice * 0.05; // 5% range

        foreach ($priceLevels as $price => $volume) {
            if ($volume > $maxLiquidation) {
                $maxLiquidation = $volume;
                $magnetPrice = $price;
            }

            if ($price >= ($currentPrice - $range) && $price <= ($currentPrice + $range)) {
                $fuel += $volume;
            }
        }

        // Generate Text
        if ($currentPrice <= 0 || empty($priceLevels)) {
            $text = "Insufficient data to generate insights for this timeframe.";
            $sentiment = "Neutral";
        } else {
            $distance = (($magnetPrice - $currentPrice) / $currentPrice) * 100;
            $direction = $distance > 0 ? 'above' : 'below';
            $sentiment = abs($distance) < 2 ? 'Critical' : 'Watching';

            $text = "The strongest liquidation magnet is at <strong>$" . number_format($magnetPrice) . "</strong> (" . number_format(abs($distance), 2) . "% {$direction}). ";
            $text .= "There is <strong>$" . $this->formatNumber($fuel) . "</strong> of liquidation fuel within 5% of current price.";
        }

        return [
            'magnet_price' => $magnetPrice,
            'magnet_strength' => $maxLiquidation,
            'local_fuel' => $fuel,
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
