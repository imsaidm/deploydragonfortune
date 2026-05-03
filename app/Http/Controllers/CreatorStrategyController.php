<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QcMethod;
use Illuminate\Support\Facades\DB;

class CreatorStrategyController extends Controller
{
    public function show($creator, Request $request)
    {
        $creator = strtolower($creator);
        $methods = QcMethod::where('creator', $creator)->where('onactive', 1)->get();

        if ($methods->isEmpty()) {
            return view('strategies.creator_empty', compact('creator'));
        }

        $strategyId = $request->query('strategy_id');
        $selectedStrategy = $strategyId ? $methods->firstWhere('id', $strategyId) : $methods->first();
        if (!$selectedStrategy) $selectedStrategy = $methods->first();

        // 1. Orders for Equity Curve
        $orders = DB::connection('methods')->table('qc_orders')
            ->where('id_method', $selectedStrategy->id)
            ->orderBy('datetime', 'asc')
            ->get();

        $chartData = $orders->map(function ($order) {
            return [
                'time'  => \Carbon\Carbon::parse($order->datetime)->getTimestamp(),
                'value' => (float) $order->balance,
            ];
        });

        // 2. All Trades → used for dots on overview chart + drilldown data
        $allTrades = DB::connection('methods')->table('qc_signal as s_entry')
            ->select(
                's_entry.id',
                's_entry.datetime',
                's_entry.jenis',
                's_entry.price_entry',
                's_entry.target_tp',
                's_entry.target_sl',
                's_entry.leverage',
                's_exit.datetime as exit_datetime',
                's_exit.price_exit as actual_price_exit'
            )
            ->leftJoin('qc_signal as s_exit', function ($join) {
                $join->on('s_exit.id', '=', DB::raw(
                    "(SELECT id FROM qc_signal WHERE id_method = s_entry.id_method AND type = 'exit' AND datetime > s_entry.datetime ORDER BY datetime ASC LIMIT 1)"
                ));
            })
            ->where('s_entry.id_method', $selectedStrategy->id)
            ->where('s_entry.type', 'entry')
            ->orderBy('s_entry.datetime', 'asc')
            ->get();

        $tpMarkers  = [];
        $slMarkers  = [];
        $tradesList = []; // full data for JS drilldown
        $tpCount = $slCount = 0;

        foreach ($allTrades as $trade) {
            $entry    = (float)$trade->price_entry;
            $exit     = (float)$trade->actual_price_exit;
            $isLong   = in_array(strtolower($trade->jenis), ['long', 'buy']);
            $isExited = $trade->actual_price_exit > 0;

            if ($isExited) {
                $pnl        = $isLong ? ($exit - $entry) : ($entry - $exit);
                $pnlPct     = $entry > 0 ? ($pnl / $entry) * 100 : 0;
                $pnlFinal   = $pnlPct * ($trade->leverage ?: 1);
                $isTpResult = $pnl >= 0;

                if ($isTpResult) $tpCount++;
                else $slCount++;

                // Find nearest equity balance for dot Y position
                $exitTime       = \Carbon\Carbon::parse($trade->exit_datetime)->getTimestamp();
                $nearestBalance = $selectedStrategy->opening_balance ?? 0;
                $smallestDiff   = PHP_INT_MAX;
                foreach ($orders as $order) {
                    $ot   = \Carbon\Carbon::parse($order->datetime)->getTimestamp();
                    $diff = abs($ot - $exitTime);
                    if ($diff < $smallestDiff) {
                        $smallestDiff = $diff;
                        $nearestBalance = (float)$order->balance;
                    }
                }

                $marker = [
                    'time'     => $exitTime,
                    'position' => $isTpResult ? 'aboveBar' : 'belowBar',
                    'color'    => $isTpResult ? '#00c853' : '#ff5252',
                    'shape'    => $isTpResult ? 'arrowUp' : 'arrowDown',
                    'text'     => $isTpResult ? ('TP ' . number_format($pnlFinal, 1) . '%') : ('SL ' . number_format($pnlFinal, 1) . '%'),
                    'balance'  => $nearestBalance,
                    'trade_id' => $trade->id,
                ];

                if ($isTpResult) $tpMarkers[] = $marker;
                else             $slMarkers[] = $marker;
            }

            // Build complete trade data for JS drilldown
            $tradesList[] = [
                'id'           => $trade->id,
                'pair'         => $selectedStrategy->pair ?? 'BTCUSDT',
                'side'         => strtolower($trade->jenis),
                'entry_price'  => $entry,
                'target_tp'    => (float)$trade->target_tp,
                'target_sl'    => (float)$trade->target_sl,
                'exit_price'   => $exit,
                'entry_time'   => \Carbon\Carbon::parse($trade->datetime)->getTimestamp(),
                'exit_time'    => $isExited ? \Carbon\Carbon::parse($trade->exit_datetime)->getTimestamp() : null,
                'is_exited'    => $isExited,
                'is_profit'    => $isExited ? ($isLong ? ($exit >= $entry) : ($entry >= $exit)) : null,
                'leverage'     => $trade->leverage ?: 1,
            ];
        }

        // Merge all markers sorted by time for Lightweight Charts
        $allMarkers = array_merge($tpMarkers, $slMarkers);
        usort($allMarkers, fn($a, $b) => $a['time'] - $b['time']);

        // 3. Paginated signals for the table
        $signals = DB::connection('methods')->table('qc_signal as s_entry')
            ->select('s_entry.*', 's_exit.datetime as exit_datetime', 's_exit.price_exit as actual_price_exit')
            ->leftJoin('qc_signal as s_exit', function ($join) {
                $join->on('s_exit.id', '=', DB::raw(
                    "(SELECT id FROM qc_signal WHERE id_method = s_entry.id_method AND type = 'exit' AND datetime > s_entry.datetime ORDER BY datetime ASC LIMIT 1)"
                ));
            })
            ->where('s_entry.id_method', $selectedStrategy->id)
            ->where('s_entry.type', 'entry')
            ->orderBy('s_entry.datetime', 'desc')
            ->paginate(10);

        return view('strategies.creator', compact(
            'creator',
            'methods',
            'selectedStrategy',
            'signals',
            'orders',
            'chartData',
            'tpCount',
            'slCount',
            'allMarkers',
            'tradesList'
        ));
    }
}
