<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QcMethod;
use Illuminate\Support\Facades\DB;

class CreatorStrategyController extends Controller
{
    public function show($creator, Request $request)
    {
        // Support creators: said, wisnu, romin, tsaqif
        $creator = strtolower($creator);

        $methods = QcMethod::where('creator', $creator)->where('onactive', 1)->get();

        if ($methods->isEmpty()) {
            return view('strategies.creator_empty', compact('creator'));
        }

        $strategyId = $request->query('strategy_id');
        if ($strategyId) {
            $selectedStrategy = $methods->firstWhere('id', $strategyId);
        } else {
            $selectedStrategy = $methods->first();
        }

        // If an invalid strategy_id was provided, fallback to the first one
        if (!$selectedStrategy) {
            $selectedStrategy = $methods->first();
        }

        // Signals history (fetch all signals for datatable)
        $signals = DB::connection('methods')->table('qc_signal')
            ->where('id_method', $selectedStrategy->id)
            ->orderBy('datetime', 'desc')
            ->paginate(10);

        // Orders history (to build equity curve / balance chart)
        $orders = DB::connection('methods')->table('qc_orders')
            ->where('id_method', $selectedStrategy->id)
            ->orderBy('datetime', 'asc')
            ->get();

        // Extract chart data
        $chartData = $orders->map(function ($order) {
            return [
                'x' => \Carbon\Carbon::parse($order->datetime)->getTimestamp() * 1000,
                'y' => (float) $order->balance
            ];
        });

        // PnL analysis over time if needed...
        // Fetch all exit signals to evaluate exactly how it exited
        $exits = DB::connection('methods')->table('qc_signal')
            ->where('id_method', $selectedStrategy->id)
            ->where('type', 'exit')
            ->get();

        $tpCount = 0;
        $slCount = 0;

        $tpPoints = [];
        $slPoints = [];

        foreach ($exits as $exit) {
            $isLong = in_array(strtolower($exit->jenis), ['long', 'buy']);
            $isTp = false;
            $isSl = false;

            // Check if real_tp and real_sl are already set clearly by system
            if ($exit->real_tp > 0) {
                $isTp = true;
            } elseif ($exit->real_sl > 0) {
                $isSl = true;
            } else {
                // Fallback manual calculation based on user instruction
                if ($isLong) {
                    if ($exit->target_tp > 0 && $exit->price_exit >= $exit->target_tp) $isTp = true;
                    elseif ($exit->price_exit > $exit->price_entry) $isTp = true;
                    else $isSl = true;
                } else {
                    if ($exit->target_tp > 0 && $exit->price_exit <= $exit->target_tp) $isTp = true;
                    elseif ($exit->price_exit > 0 && $exit->price_exit < $exit->price_entry) $isTp = true;
                    else $isSl = true;
                }
            }

            if ($isTp) $tpCount++;
            if ($isSl) $slCount++;

            // Find nearest balance for dot annotation on chart
            if ($isTp || $isSl) {
                $exitTime = \Carbon\Carbon::parse($exit->datetime)->getTimestamp();
                $nearestBalance = $selectedStrategy->opening_balance ?? 0;
                $smallestDiff = PHP_INT_MAX;

                foreach ($orders as $order) {
                    $orderTime = \Carbon\Carbon::parse($order->datetime)->getTimestamp();
                    $diff = abs($orderTime - $exitTime);
                    if ($diff < $smallestDiff) {
                        $smallestDiff = $diff;
                        $nearestBalance = (float)$order->balance;
                    }
                }

                $point = ['x' => $exitTime * 1000, 'y' => $nearestBalance];
                if ($isTp) {
                    $tpPoints[] = $point;
                } else {
                    $slPoints[] = $point;
                }
            }
        }

        return view('strategies.creator', compact(
            'creator',
            'methods',
            'selectedStrategy',
            'signals',
            'orders',
            'chartData',
            'tpCount',
            'slCount',
            'tpPoints',
            'slPoints'
        ));
    }
}
