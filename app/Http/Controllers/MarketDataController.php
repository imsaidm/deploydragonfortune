<?php

namespace App\Http\Controllers;

use App\Jobs\CrawlMarketDataJob;
use Illuminate\Http\Request;

class MarketDataController extends Controller
{
    public function index()
    {
        $datasets = \App\Models\MarketCandle::query()
            ->selectRaw('exchange, type, symbol, timeframe, COUNT(*) as total_candles, MIN(timestamp) as oldest_ts, MAX(timestamp) as newest_ts')
            ->groupBy('exchange', 'type', 'symbol', 'timeframe')
            ->orderBy('exchange')->orderBy('type')->orderBy('symbol')->orderBy('timeframe')
            ->get();

        return view('market-data.crawler', compact('datasets'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'exchange'   => ['required', 'in:binance,bybit'],
            'type'       => ['required', 'in:spot,future'],
            'symbol'     => ['required', 'string', 'max:20'],
            'timeframe'  => ['required', 'in:1m,3m,5m,15m,30m,1h,4h,1d'],
            'start_date' => ['required', 'date', 'before_or_equal:end_date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $symbol = strtoupper(trim($validated['symbol']));
        if (!str_contains($symbol, '/')) {
            $symbol = preg_replace('/^([A-Z]+)(USDT|USDC|BTC|ETH|BNB)$/', '$1/$2', $symbol);
        }

        CrawlMarketDataJob::dispatch(
            $validated['exchange'],
            $validated['type'],
            $symbol,
            $validated['timeframe'],
            $validated['start_date'],
            $validated['end_date'],
        )->onQueue('crawler');

        return back()->with('success',
            "✅ Job dispatched! Crawling {$symbol} ({$validated['exchange']} {$validated['type']}) "
            . "{$validated['timeframe']} from {$validated['start_date']} to {$validated['end_date']}."
        );
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'exchange'  => ['required', 'in:binance,bybit'],
            'type'      => ['required', 'in:spot,future'],
            'symbol'    => ['required', 'string'],
            'timeframe' => ['required', 'string'],
        ]);

        $deleted = \App\Models\MarketCandle::where('exchange',  $validated['exchange'])
            ->where('type',      $validated['type'])
            ->where('symbol',    $validated['symbol'])
            ->where('timeframe', $validated['timeframe'])
            ->delete();

        return back()->with('success',
            "🗑️ Deleted {$deleted} candles for {$validated['symbol']} "
            . "({$validated['exchange']} {$validated['type']}, {$validated['timeframe']})."
        );
    }

    public function priceChecker(Request $request)
    {
        // Available datasets for dropdown
        $datasets = \App\Models\MarketCandle::query()
            ->selectRaw('exchange, type, symbol, timeframe, MIN(timestamp) as oldest_ts, MAX(timestamp) as newest_ts')
            ->groupBy('exchange', 'type', 'symbol', 'timeframe')
            ->orderBy('symbol')->orderBy('timeframe')
            ->get();

        return view('market-data.price-checker', compact('datasets'));
    }

    public function priceCheck(Request $request)
    {
        $validated = $request->validate([
            'exchange'    => ['required', 'string'],
            'type'        => ['required', 'string'],
            'symbol'      => ['required', 'string'],
            'timeframe'   => ['required', 'string'],
            'direction'   => ['required', 'in:long,short'],
            'entry_price' => ['required', 'numeric', 'min:0'],
            'tp_price'    => ['nullable', 'numeric', 'min:0'],
            'sl_price'    => ['nullable', 'numeric', 'min:0'],
            'from_date'   => ['required', 'date'],
            'to_date'     => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $fromMs = (int) (strtotime($validated['from_date']) * 1000);
        $toMs   = (int) (strtotime($validated['to_date'] . ' 23:59:59') * 1000);
        $dir    = $validated['direction'];
        $entry  = (float) $validated['entry_price'];
        $tp     = $validated['tp_price'] ? (float) $validated['tp_price'] : null;
        $sl     = $validated['sl_price'] ? (float) $validated['sl_price'] : null;

        // Fetch candles in range
        $candles = \App\Models\MarketCandle::query()
            ->where('exchange',  $validated['exchange'])
            ->where('type',      $validated['type'])
            ->where('symbol',    $validated['symbol'])
            ->where('timeframe', $validated['timeframe'])
            ->whereBetween('timestamp', [$fromMs, $toMs])
            ->orderBy('timestamp')
            ->get(['timestamp', 'open', 'high', 'low', 'close']);

        // Walk candles to find first TP / SL touch
        $firstTp = null;
        $firstSl = null;
        $timeline = [];
        $resolved = false;

        foreach ($candles as $c) {
            $wib = \Carbon\Carbon::createFromTimestampMs($c->timestamp)->addHours(7);

            $hitTp = $tp !== null && ($dir === 'long'  ? $c->high >= $tp : $c->low  <= $tp);
            $hitSl = $sl !== null && ($dir === 'long'  ? $c->low  <= $sl : $c->high >= $sl);

            if ($hitTp && !$firstTp) $firstTp = ['candle' => $c, 'wib' => $wib];
            if ($hitSl && !$firstSl) $firstSl = ['candle' => $c, 'wib' => $wib];

            // Collect notable candles (near TP/SL within 1%)
            $nearTp = $tp && abs($c->low  - $tp) / $tp < 0.01;
            $nearSl = $sl && abs($c->high - $sl) / $sl < 0.01;
            if ($hitTp || $hitSl || $nearTp || $nearSl) {
                $timeline[] = [
                    'wib'    => $wib,
                    'open'   => $c->open,
                    'high'   => $c->high,
                    'low'    => $c->low,
                    'close'  => $c->close,
                    'hit_tp' => $hitTp,
                    'hit_sl' => $hitSl,
                ];
            }
        }

        // Determine outcome
        $outcome = 'open'; // still in trade
        if ($firstTp && $firstSl) {
            $outcome = $firstTp['candle']->timestamp <= $firstSl['candle']->timestamp ? 'tp' : 'sl';
        } elseif ($firstTp) {
            $outcome = 'tp';
        } elseif ($firstSl) {
            $outcome = 'sl';
        }

        $datasets = \App\Models\MarketCandle::query()
            ->selectRaw('exchange, type, symbol, timeframe, MIN(timestamp) as oldest_ts, MAX(timestamp) as newest_ts')
            ->groupBy('exchange', 'type', 'symbol', 'timeframe')
            ->orderBy('symbol')->orderBy('timeframe')
            ->get();

        return view('market-data.price-checker', compact(
            'datasets', 'validated', 'candles',
            'firstTp', 'firstSl', 'outcome', 'timeline',
            'entry', 'tp', 'sl', 'dir'
        ));
    }
}
