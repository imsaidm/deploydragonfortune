<?php

namespace App\Http\Controllers;

use App\Jobs\CrawlMarketDataJob;
use App\Models\MarketCandle;
use App\Models\QcMethod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function candleSyncStatus()
    {
        $datasets = MarketCandle::query()
            ->selectRaw('exchange, type, symbol, timeframe, COUNT(*) as total_candles, MIN(timestamp) as oldest_ts, MAX(timestamp) as newest_ts, MAX(updated_at) as last_updated_at')
            ->groupBy('exchange', 'type', 'symbol', 'timeframe')
            ->orderBy('exchange')
            ->orderBy('type')
            ->orderBy('symbol')
            ->orderByRaw("FIELD(timeframe, '1m', '3m', '5m', '10m', '15m', '30m', '1h', '4h', '1d')")
            ->get()
            ->map(function ($dataset) {
                $durationMs = MarketCandle::timeframeDurationMs($dataset->timeframe);
                $newest = Carbon::createFromTimestampMs((int) $dataset->newest_ts);
                $oldest = Carbon::createFromTimestampMs((int) $dataset->oldest_ts);
                $lagSeconds = $newest->diffInSeconds(now(), false);
                $isStale = $lagSeconds > max(600, ($durationMs / 1000) * 3);
                $gap = $this->largestRecentGap($dataset->exchange, $dataset->type, $dataset->symbol, $dataset->timeframe, $durationMs);

                return (object) [
                    'exchange' => $dataset->exchange,
                    'type' => $dataset->type,
                    'symbol' => $dataset->symbol,
                    'timeframe' => $dataset->timeframe,
                    'total_candles' => (int) $dataset->total_candles,
                    'oldest' => $oldest,
                    'newest' => $newest,
                    'last_updated_at' => $dataset->last_updated_at ? Carbon::parse($dataset->last_updated_at) : null,
                    'lag_seconds' => $lagSeconds,
                    'is_stale' => $isStale,
                    'largest_gap_ms' => $gap,
                    'has_gap' => $gap > ($durationMs * 2),
                    'expected_gap_ms' => $durationMs,
                    'is_active_strategy_market' => false,
                ];
            });

        $activeMarkets = $this->activeStrategyMarkets();
        $activeKeys = $activeMarkets->pluck('key')->flip();

        $datasets = $datasets->map(function ($dataset) use ($activeKeys) {
            $key = $this->marketKey($dataset->exchange, $dataset->type, $dataset->symbol, $dataset->timeframe);
            $dataset->is_active_strategy_market = $activeKeys->has($key);

            return $dataset;
        });

        $datasetKeys = $datasets
            ->map(fn($dataset) => $this->marketKey($dataset->exchange, $dataset->type, $dataset->symbol, $dataset->timeframe))
            ->flip();

        $missingActiveMarkets = $activeMarkets
            ->reject(fn($market) => $datasetKeys->has($market['key']))
            ->values();

        $summary = [
            'datasets' => $datasets->count(),
            'active_markets' => $activeMarkets->count(),
            'missing_active_markets' => $missingActiveMarkets->count(),
            'stale' => $datasets->where('is_stale', true)->count(),
            'with_gaps' => $datasets->where('has_gap', true)->count(),
            'total_candles' => $datasets->sum('total_candles'),
        ];

        return view('market-data.candle-sync-status', compact('datasets', 'activeMarkets', 'missingActiveMarkets', 'summary'));
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
        );

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

    private function largestRecentGap(string $exchange, string $type, string $symbol, string $timeframe, int $durationMs): int
    {
        $timestamps = MarketCandle::query()
            ->where('exchange', $exchange)
            ->where('type', $type)
            ->where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->orderByDesc('timestamp')
            ->limit(500)
            ->pluck('timestamp')
            ->sort()
            ->values();

        if ($timestamps->count() < 2) {
            return 0;
        }

        $largest = 0;
        for ($i = 1; $i < $timestamps->count(); $i++) {
            $largest = max($largest, (int) $timestamps[$i] - (int) $timestamps[$i - 1]);
        }

        return max(0, $largest - $durationMs);
    }

    private function activeStrategyMarkets()
    {
        return QcMethod::query()
            ->where('onactive', 1)
            ->whereNotNull('pair')
            ->where('pair', '<>', '')
            ->get()
            ->map(function ($method) {
                $symbol = $this->slashSymbol($this->normalizeSymbol((string) $method->pair));
                $exchange = str_contains(strtolower((string) $method->exchange), 'bybit') ? 'bybit' : 'binance';
                $type = $this->marketType($method);
                $timeframe = strtolower($method->tf ?: '1h');

                return [
                    'strategy_id' => $method->id,
                    'strategy_name' => $method->nama_metode,
                    'exchange' => $exchange,
                    'type' => $type,
                    'symbol' => $symbol,
                    'timeframe' => $timeframe,
                    'key' => $this->marketKey($exchange, $type, $symbol, $timeframe),
                ];
            })
            ->filter(fn($market) => $market['symbol'])
            ->unique('key')
            ->values();
    }

    private function marketType(QcMethod $method): string
    {
        $latestSignalMarket = DB::connection('methods')->table('qc_signal')
            ->where('id_method', $method->id)
            ->whereNotNull('market_type')
            ->where('market_type', '<>', '')
            ->orderByDesc('datetime')
            ->value('market_type');

        $haystack = strtolower(($method->nama_metode ?? '') . ' ' . ($method->exchange ?? '') . ' ' . ($latestSignalMarket ?? ''));

        return str_contains($haystack, 'future') || str_contains($haystack, 'perp') ? 'future' : 'spot';
    }

    private function marketKey(string $exchange, string $type, string $symbol, string $timeframe): string
    {
        return strtolower("{$exchange}|{$type}|{$symbol}|{$timeframe}");
    }

    private function normalizeSymbol(string $pair): string
    {
        $symbol = strtoupper(preg_replace('/[^A-Z0-9]/', '', $pair));

        return preg_match('/(USDT|USDC|BUSD|USD)$/', $symbol) ? $symbol : $symbol . 'USDT';
    }

    private function slashSymbol(string $symbol): string
    {
        foreach (['USDT', 'USDC', 'BUSD', 'USD'] as $quote) {
            if (str_ends_with($symbol, $quote)) {
                return substr($symbol, 0, -strlen($quote)) . '/' . $quote;
            }
        }

        return '';
    }
}
