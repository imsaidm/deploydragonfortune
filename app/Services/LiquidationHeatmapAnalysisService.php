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
        $yAxis = $heatmap->yAxis()->orderBy('sequence_order')->get(); // Maps sequence_order -> price_level
        $leverageData = $heatmap->leverageData()->get(); // Maps x,y -> value
        $candles = $heatmap->candlesticks()->orderBy('timestamp')->get();

        // 3. Transform Data for Chartjs Matrix
        // We need: { x: timestamp, y: price, v: value }
        $chartData = [];
        $yMap = $yAxis->pluck('price_level', 'sequence_order'); // optimize lookup

        // Aggregation for Insights
        $priceLevelsParams = []; // [price => total_liquidation]

        foreach ($leverageData as $point) {
            $price = $yMap[$point->y_position] ?? 0;
            if ($price == 0) continue;

            $chartData[] = [
                'x' => $point->x_position * 1000,
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
        $priceLine = $candles->map(function ($c) {
            return [
                'x' => $c->timestamp * 1000,
                'y' => $c->close_price
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
