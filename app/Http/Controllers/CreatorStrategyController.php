<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QcMethod;
use App\Models\QcSignal;
use App\Models\MarketCandle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class CreatorStrategyController extends Controller
{
    public function show($creator, Request $request)
    {
        $creator = strtolower($creator);
        $methods = QcMethod::whereRaw('LOWER(creator) = ?', [$creator])
            ->where('onactive', 1)
            ->get();

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
                'time'  => Carbon::parse($order->datetime)->getTimestamp(),
                'value' => (float) $order->balance,
            ];
        });

        // 2. All Trades → used for dots on overview chart + drilldown data
        $allTrades = DB::connection('methods')->table('qc_signal as s_entry')
            ->select(
                's_entry.id',
                's_entry.datetime',
                's_entry.created_at as entry_created_at',
                's_entry.jenis',
                's_entry.price_entry',
                's_entry.target_tp',
                's_entry.target_sl',
                's_entry.leverage',
                's_entry.market_type',
                's_exit.datetime as exit_datetime',
                's_exit.created_at as exit_created_at',
                's_exit.price_exit as actual_price_exit'
            )
            ->leftJoin('qc_signal as s_exit', function ($join) {
                $join->on('s_exit.id', '=', DB::raw(
                    "(SELECT id FROM qc_signal WHERE id_method = s_entry.id_method AND type = 'exit' AND created_at > s_entry.created_at ORDER BY created_at ASC LIMIT 1)"
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
                $exitTime       = $this->signalEventTimestamp($trade->exit_created_at, $trade->exit_datetime);
                $nearestBalance = $selectedStrategy->opening_balance ?? 0;
                $smallestDiff   = PHP_INT_MAX;
                foreach ($orders as $order) {
                    $ot   = Carbon::parse($order->datetime)->getTimestamp();
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
                'entry_time'   => $this->signalEventTimestamp($trade->entry_created_at, $trade->datetime),
                'exit_time'    => $isExited ? $this->signalEventTimestamp($trade->exit_created_at, $trade->exit_datetime) : null,
                'qc_entry_time' => Carbon::parse($trade->datetime)->getTimestamp(),
                'qc_exit_time'  => $isExited ? Carbon::parse($trade->exit_datetime)->getTimestamp() : null,
                'is_exited'    => $isExited,
                'is_profit'    => $isExited ? ($isLong ? ($exit >= $entry) : ($entry >= $exit)) : null,
                'leverage'     => $trade->leverage ?: 1,
                'market_type'  => strtolower($trade->market_type ?: ''),
            ];
        }

        // Merge all markers sorted by time for Lightweight Charts
        $allMarkers = array_merge($tpMarkers, $slMarkers);
        usort($allMarkers, fn($a, $b) => $a['time'] - $b['time']);

        $entryMarkers = collect($tradesList)->map(function ($trade) {
            $isLong = str_contains($trade['side'], 'long') || str_contains($trade['side'], 'buy');

            return [
                'time' => $trade['entry_time'],
                'position' => $isLong ? 'belowBar' : 'aboveBar',
                'color' => $isLong ? '#16a34a' : '#ef4444',
                'shape' => 'circle',
                'text' => strtoupper($isLong ? 'LONG' : 'SHORT') . ' Entry $' . number_format($trade['entry_price'], 2),
                'trade_id' => $trade['id'],
                'signal_type' => 'entry',
            ];
        })->values()->all();

        $exitMarkers = collect($tradesList)->filter(fn($trade) => $trade['is_exited'])->map(function ($trade) {
            $isProfit = (bool) $trade['is_profit'];

            return [
                'time' => $trade['exit_time'],
                'position' => $isProfit ? 'aboveBar' : 'belowBar',
                'color' => $isProfit ? '#22c55e' : '#f43f5e',
                'shape' => $isProfit ? 'arrowUp' : 'arrowDown',
                'text' => ($isProfit ? 'TP' : 'SL') . ' Exit $' . number_format($trade['exit_price'], 2),
                'trade_id' => $trade['id'],
                'signal_type' => 'exit',
            ];
        })->values()->all();

        $signalMarkers = array_merge($entryMarkers, $exitMarkers);
        usort($signalMarkers, fn($a, $b) => $a['time'] <=> $b['time']);

        $strategyMeta = $this->buildStrategyMeta($selectedStrategy, $tradesList);
        $timeframeOptions = $this->timeframeOptions($selectedStrategy->tf ?? '1h');
        $latestTrade = collect($tradesList)->sortByDesc('entry_time')->first();
        $activeTrades = collect($tradesList)->where('is_exited', false)->count();

        // 3. Paginated signals for the table
        $signals = DB::connection('methods')->table('qc_signal as s_entry')
            ->select('s_entry.*', 's_exit.datetime as exit_datetime', 's_exit.created_at as exit_created_at', 's_exit.price_exit as actual_price_exit')
            ->leftJoin('qc_signal as s_exit', function ($join) {
                $join->on('s_exit.id', '=', DB::raw(
                    "(SELECT id FROM qc_signal WHERE id_method = s_entry.id_method AND type = 'exit' AND created_at > s_entry.created_at ORDER BY created_at ASC LIMIT 1)"
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
            'tradesList',
            'signalMarkers',
            'strategyMeta',
            'timeframeOptions',
            'latestTrade',
            'activeTrades'
        ));
    }

    private function signalEventTimestamp(?string $createdAt, ?string $fallbackDatetime): int
    {
        return Carbon::parse($createdAt ?: $fallbackDatetime)->getTimestamp();
    }

    public function candles(QcMethod $strategy, Request $request)
    {
        $timeframes = $this->timeframeOptions($strategy->tf ?? '1h');
        $validated = $request->validate([
            'interval' => ['nullable', Rule::in($timeframes)],
            'start' => ['nullable', 'integer'],
            'end' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:50', 'max:5000'],
        ]);

        $interval = $validated['interval'] ?? ($strategy->tf ?: '1h');
        if (!in_array($interval, $timeframes, true)) {
            $interval = end($timeframes) ?: '1h';
        }

        $limit = $validated['limit'] ?? 700;
        $endMs = $validated['end'] ?? now()->valueOf();
        $startMs = $validated['start'] ?? ($endMs - ($this->timeframeMs($interval) * $limit));
        $meta = $this->buildStrategyMeta($strategy, $this->latestSignalContext($strategy->id));

        $candles = $this->localCandles($meta['exchange'], $meta['market_type'], $meta['db_symbol'], $interval, $startMs, $endMs, $limit);
        $source = 'database';

        $lastLocalTime = $candles->max('time');
        $isStale = $lastLocalTime && (($lastLocalTime * 1000) < ($endMs - ($this->timeframeMs($interval) * 2)));

        if ($candles->count() < 20) {
            $candles = collect($this->exchangeCandles($meta['exchange'], $meta['market_type'], $meta['symbol'], $interval, $startMs, $endMs, $limit));
            $this->persistCandles($meta['exchange'], $meta['market_type'], $meta['db_symbol'], $interval, $candles);
            $source = $candles->isEmpty() ? 'unavailable' : $meta['exchange'] . '_api';
        } elseif ($isStale && $candles->count() < $limit) {
            $remoteStart = ($lastLocalTime * 1000) + $this->timeframeMs($interval);
            $remoteLimit = $limit - $candles->count();
            $remoteCandles = collect($this->exchangeCandles($meta['exchange'], $meta['market_type'], $meta['symbol'], $interval, $remoteStart, $endMs, $remoteLimit));

            if ($remoteCandles->isNotEmpty()) {
                $this->persistCandles($meta['exchange'], $meta['market_type'], $meta['db_symbol'], $interval, $remoteCandles);
                $candles = $candles
                    ->concat($remoteCandles)
                    ->unique('time')
                    ->sortBy('time')
                    ->values();
                $source = 'database+' . $meta['exchange'] . '_api';
            }
        }

        return response()->json([
            'strategy_id' => $strategy->id,
            'symbol' => $meta['symbol'],
            'exchange' => $meta['exchange'],
            'market_type' => $meta['market_type'],
            'interval' => $interval,
            'source' => $source,
            'candles' => $candles->values(),
        ]);
    }

    public function ticker(QcMethod $strategy)
    {
        $meta = $this->buildStrategyMeta($strategy, $this->latestSignalContext($strategy->id));
        $price = $meta['exchange'] === 'bybit'
            ? $this->bybitTicker($meta['market_type'], $meta['symbol'])
            : $this->binanceTicker($meta['market_type'], $meta['symbol']);

        return response()->json([
            'strategy_id' => $strategy->id,
            'symbol' => $meta['symbol'],
            'exchange' => $meta['exchange'],
            'market_type' => $meta['market_type'],
            'price' => $price,
            'time' => now()->valueOf(),
        ]);
    }

    public function forceExit(QcMethod $strategy, Request $request)
    {
        $validated = $request->validate([
            'price_exit' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $meta = $this->buildStrategyMeta($strategy, $this->latestSignalContext($strategy->id));
        $price = isset($validated['price_exit']) ? (float) $validated['price_exit'] : null;

        if (! $price || $price <= 0) {
            $price = $meta['exchange'] === 'bybit'
                ? $this->bybitTicker($meta['market_type'], $meta['symbol'])
                : $this->binanceTicker($meta['market_type'], $meta['symbol']);
        }

        if (! $price || $price <= 0) {
            return response()->json([
                'message' => 'Live exit price is unavailable. Please wait for the price stream and try again.',
            ], 422);
        }

        $activeEntry = $this->latestOpenEntrySignal($strategy->id);
        if (! $activeEntry) {
            return response()->json([
                'message' => 'No active entry signal found for this strategy.',
            ], 422);
        }

        $signal = QcSignal::create([
            'id_method' => $strategy->id,
            'datetime' => now(),
            'type' => 'exit',
            'jenis' => $activeEntry->jenis ?: 'sell',
            'leverage' => $activeEntry->leverage ?: 1,
            'price_entry' => $activeEntry->price_entry ?: 0,
            'price_exit' => $price,
            'target_tp' => $activeEntry->target_tp ?: 0,
            'target_sl' => $activeEntry->target_sl ?: 0,
            'real_tp' => 0,
            'real_sl' => 0,
            'quantity' => $activeEntry->quantity ?: 0,
            'ratio' => 0,
            'market_type' => $activeEntry->market_type ?: $meta['market_type'],
            'force_exit' => true,
            'message' => 'Manual force exit from strategy page at $' . number_format($price, 8, '.', ''),
            'telegram_sent' => 0,
            'telegram_processing' => 0,
        ]);

        return response()->json([
            'message' => 'Force exit signal created.',
            'signal' => [
                'id' => $signal->id,
                'id_method' => $signal->id_method,
                'type' => $signal->type,
                'jenis' => $signal->jenis,
                'price_exit' => (float) $signal->price_exit,
                'force_exit' => (bool) $signal->force_exit,
                'market_type' => $signal->market_type,
            ],
        ], 201);
    }

    private function latestSignalContext(int $strategyId): array
    {
        $signal = DB::connection('methods')->table('qc_signal')
            ->where('id_method', $strategyId)
            ->whereNotNull('market_type')
            ->where('market_type', '<>', '')
            ->orderByDesc('datetime')
            ->first(['market_type']);

        return $signal ? [['market_type' => strtolower($signal->market_type)]] : [];
    }

    private function latestOpenEntrySignal(int $strategyId): ?object
    {
        return DB::connection('methods')->table('qc_signal as s_entry')
            ->where('s_entry.id_method', $strategyId)
            ->where('s_entry.type', 'entry')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('qc_signal as s_exit')
                    ->whereColumn('s_exit.id_method', 's_entry.id_method')
                    ->where('s_exit.type', 'exit')
                    ->whereColumn('s_exit.created_at', '>', 's_entry.created_at');
            })
            ->orderByDesc('s_entry.created_at')
            ->first([
                's_entry.id',
                's_entry.jenis',
                's_entry.leverage',
                's_entry.price_entry',
                's_entry.target_tp',
                's_entry.target_sl',
                's_entry.quantity',
                's_entry.market_type',
            ]);
    }

    private function buildStrategyMeta(QcMethod $strategy, array $tradesList): array
    {
        $symbol = $this->normalizeSymbol($strategy->pair ?: 'BTCUSDT');
        $exchange = str_contains(strtolower((string) $strategy->exchange), 'bybit') ? 'bybit' : 'binance';
        $latestMarketType = collect($tradesList)->pluck('market_type')->filter()->first();
        $name = strtolower(($strategy->nama_metode ?? '') . ' ' . ($strategy->exchange ?? '') . ' ' . ($latestMarketType ?? ''));
        $marketType = str_contains($name, 'future') || str_contains($name, 'perp') ? 'future' : 'spot';

        return [
            'id' => $strategy->id,
            'name' => $strategy->nama_metode,
            'symbol' => $symbol,
            'base_asset' => preg_replace('/(USDT|USDC|BUSD|USD)$/', '', $symbol),
            'quote_asset' => str_ends_with($symbol, 'USDT') ? 'USDT' : 'USD',
            'db_symbol' => $this->slashSymbol($symbol),
            'exchange' => $exchange,
            'market_type' => $marketType,
            'base_tf' => strtolower($strategy->tf ?: '1h'),
            'description' => $strategy->description,
            'quantconnect_url' => $strategy->url,
            'notification_thresholds' => [
                'up_percentage' => (float) $strategy->notify_up_percentage,
                'down_percentage' => (float) $strategy->notify_down_percentage,
            ],
            'metrics' => [
                'cagr' => (float) $strategy->cagr,
                'drawdown' => (float) $strategy->drawdown,
                'winrate' => (float) $strategy->winrate,
                'sharpe' => (float) $strategy->sharpen_ratio,
                'sortino' => (float) $strategy->sortino_ratio,
                'orders' => (float) $strategy->total_orders,
            ],
        ];
    }

    private function localCandles(string $exchange, string $marketType, string $dbSymbol, string $interval, int $startMs, int $endMs, int $limit)
    {
        if (in_array($interval, ['5m', '10m', '30m', '1h'], true)) {
            return $this->directOrAggregatedCandles($exchange, $marketType, $dbSymbol, $interval, $startMs, $endMs, $limit);
        }

        return MarketCandle::query()
            ->where('exchange', $exchange)
            ->where('type', $marketType)
            ->where('symbol', $dbSymbol)
            ->where('timeframe', $interval)
            ->whereBetween('timestamp', [$startMs, $endMs])
            ->orderBy('timestamp')
            ->limit($limit)
            ->get(['timestamp', 'open', 'high', 'low', 'close', 'volume'])
            ->map(fn($row) => $this->formatCandle($row));
    }

    private function directOrAggregatedCandles(string $exchange, string $marketType, string $dbSymbol, string $interval, int $startMs, int $endMs, int $limit)
    {
        $directCandles = MarketCandle::query()
            ->where('exchange', $exchange)
            ->where('type', $marketType)
            ->where('symbol', $dbSymbol)
            ->where('timeframe', $interval)
            ->whereBetween('timestamp', [$startMs, $endMs])
            ->orderBy('timestamp')
            ->limit($limit)
            ->get(['timestamp', 'open', 'high', 'low', 'close', 'volume']);

        if ($this->hasUsableCandleCoverage($directCandles, $interval, $startMs, $endMs, $limit)) {
            return $directCandles->map(fn($row) => $this->formatCandle($row));
        }

        return $this->aggregateCandlesFromOneMinute($exchange, $marketType, $dbSymbol, $interval, $startMs, $endMs, $limit);
    }

    private function hasUsableCandleCoverage($candles, string $interval, int $startMs, int $endMs, int $limit): bool
    {
        $minimum = min($limit, 20);
        if ($candles->count() < $minimum) {
            return false;
        }

        $intervalMs = $this->timeframeMs($interval);
        $firstTimestamp = (int) $candles->first()->timestamp;
        $lastTimestamp = (int) $candles->last()->timestamp;
        $requestedSpan = $endMs - $startMs;

        if ($requestedSpan > ($intervalMs * $minimum) && $firstTimestamp > ($startMs + ($intervalMs * 2))) {
            return false;
        }

        if ($lastTimestamp < ($endMs - ($intervalMs * 2))) {
            return false;
        }

        $previousTimestamp = null;
        foreach ($candles as $candle) {
            $timestamp = (int) $candle->timestamp;
            if ($previousTimestamp !== null && ($timestamp - $previousTimestamp) > ($intervalMs * 2)) {
                return false;
            }

            $previousTimestamp = $timestamp;
        }

        return true;
    }

    private function aggregateCandlesFromOneMinute(string $exchange, string $marketType, string $dbSymbol, string $interval, int $startMs, int $endMs, int $limit)
    {
        $bucketMs = $this->timeframeMs($interval);
        $oneMinuteLimit = max($limit, (int) ceil(($endMs - $startMs) / 60_000) + 5);

        return MarketCandle::query()
            ->where('exchange', $exchange)
            ->where('type', $marketType)
            ->where('symbol', $dbSymbol)
            ->where('timeframe', '1m')
            ->whereBetween('timestamp', [$startMs, $endMs])
            ->orderBy('timestamp')
            ->limit($oneMinuteLimit)
            ->get(['timestamp', 'open', 'high', 'low', 'close', 'volume'])
            ->groupBy(fn($row) => (int) floor($row->timestamp / $bucketMs) * $bucketMs)
            ->map(function ($group, $bucket) {
                $sorted = $group->sortBy('timestamp')->values();

                return [
                    'time' => (int) floor($bucket / 1000),
                    'open' => (float) $sorted->first()->open,
                    'high' => (float) $sorted->max('high'),
                    'low' => (float) $sorted->min('low'),
                    'close' => (float) $sorted->last()->close,
                    'volume' => (float) $sorted->sum('volume'),
                ];
            })
            ->sortBy('time')
            ->take($limit)
            ->values();
    }

    private function exchangeCandles(string $exchange, string $marketType, string $symbol, string $interval, int $startMs, int $endMs, int $limit): array
    {
        if ($interval === '10m') {
            $oneMinute = $this->exchangeCandles($exchange, $marketType, $symbol, '1m', $startMs, $endMs, min($limit * 10, 1500));
            return collect($oneMinute)
                ->groupBy(fn($row) => (int) floor($row['time'] / 600) * 600)
                ->map(function ($group, $bucket) {
                    $sorted = $group->sortBy('time')->values();

                    return [
                        'time' => (int) $bucket,
                        'open' => (float) $sorted->first()['open'],
                        'high' => (float) $sorted->max('high'),
                        'low' => (float) $sorted->min('low'),
                        'close' => (float) $sorted->last()['close'],
                        'volume' => (float) $sorted->sum('volume'),
                    ];
                })
                ->values()
                ->all();
        }

        return $exchange === 'bybit'
            ? $this->bybitCandles($symbol, $interval, $startMs, $endMs, $limit)
            : $this->binanceCandles($marketType, $symbol, $interval, $startMs, $endMs, $limit);
    }

    private function binanceCandles(string $marketType, string $symbol, string $interval, int $startMs, int $endMs, int $limit): array
    {
        $baseUrl = $marketType === 'future' ? 'https://fapi.binance.com/fapi/v1/klines' : 'https://api.binance.com/api/v3/klines';
        $rows = [];
        $cursor = $startMs;

        while ($cursor <= $endMs && count($rows) < $limit) {
            try {
                $response = Http::timeout(8)->get($baseUrl, [
                    'symbol' => $symbol,
                    'interval' => $interval,
                    'startTime' => $cursor,
                    'endTime' => $endMs,
                    'limit' => min($limit - count($rows), 1500),
                ]);
            } catch (\Throwable $e) {
                break;
            }

            $batch = $response->json();
            if (!$response->ok() || !is_array($batch) || empty($batch)) {
                break;
            }

            foreach ($batch as $row) {
                $rows[] = [
                    'time' => (int) floor($row[0] / 1000),
                    'open' => (float) $row[1],
                    'high' => (float) $row[2],
                    'low' => (float) $row[3],
                    'close' => (float) $row[4],
                    'volume' => (float) $row[5],
                ];
            }

            $lastRow = end($batch);
            $lastOpen = (int) $lastRow[0];
            $nextCursor = $lastOpen + $this->timeframeMs($interval);
            if ($nextCursor <= $cursor) {
                break;
            }
            $cursor = $nextCursor;
        }

        return $rows;
    }

    private function bybitCandles(string $symbol, string $interval, int $startMs, int $endMs, int $limit): array
    {
        $rows = [];
        $cursor = $startMs;

        while ($cursor <= $endMs && count($rows) < $limit) {
            try {
                $response = Http::timeout(8)->get('https://api.bybit.com/v5/market/kline', [
                    'category' => 'linear',
                    'symbol' => $symbol,
                    'interval' => $this->bybitInterval($interval),
                    'start' => $cursor,
                    'end' => $endMs,
                    'limit' => min($limit - count($rows), 1000),
                ]);
            } catch (\Throwable $e) {
                break;
            }

            $batch = $response->json('result.list');
            if (!$response->ok() || !is_array($batch) || empty($batch)) {
                break;
            }

            $formatted = collect($batch)->map(fn($row) => [
                'time' => (int) floor($row[0] / 1000),
                'open' => (float) $row[1],
                'high' => (float) $row[2],
                'low' => (float) $row[3],
                'close' => (float) $row[4],
                'volume' => (float) $row[5],
            ])->sortBy('time')->values()->all();

            $rows = array_merge($rows, $formatted);
            $last = end($formatted);
            $nextCursor = ($last['time'] * 1000) + $this->timeframeMs($interval);
            if ($nextCursor <= $cursor) {
                break;
            }
            $cursor = $nextCursor;
        }

        return collect($rows)->unique('time')->sortBy('time')->values()->all();
    }

    private function binanceTicker(string $marketType, string $symbol): ?float
    {
        $baseUrl = $marketType === 'future'
            ? 'https://fapi.binance.com/fapi/v1/ticker/price'
            : 'https://api.binance.com/api/v3/ticker/price';

        try {
            $response = Http::timeout(5)->get($baseUrl, ['symbol' => $symbol]);
        } catch (\Throwable $e) {
            return null;
        }

        if (!$response->ok()) {
            return null;
        }

        $price = (float) $response->json('price');

        return $price > 0 ? $price : null;
    }

    private function bybitTicker(string $marketType, string $symbol): ?float
    {
        try {
            $response = Http::timeout(5)->get('https://api.bybit.com/v5/market/tickers', [
                'category' => $marketType === 'spot' ? 'spot' : 'linear',
                'symbol' => $symbol,
            ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (!$response->ok()) {
            return null;
        }

        $ticker = collect($response->json('result.list'))->first();
        $price = (float) ($ticker['lastPrice'] ?? 0);

        return $price > 0 ? $price : null;
    }

    private function formatCandle(MarketCandle $row): array
    {
        return [
            'time' => (int) floor($row->timestamp / 1000),
            'open' => (float) $row->open,
            'high' => (float) $row->high,
            'low' => (float) $row->low,
            'close' => (float) $row->close,
            'volume' => (float) $row->volume,
        ];
    }

    private function persistCandles(string $exchange, string $marketType, string $dbSymbol, string $interval, $candles): void
    {
        $rows = collect($candles)
            ->filter(fn($row) => isset($row['time'], $row['open'], $row['high'], $row['low'], $row['close']))
            ->take(1500);

        if ($rows->isEmpty()) {
            return;
        }

        $builder = (new MarketCandle())->getConnection()->getSchemaBuilder();
        $hasCreatedAt = $builder->hasColumn((new MarketCandle())->getTable(), 'created_at');
        $hasUpdatedAt = $builder->hasColumn((new MarketCandle())->getTable(), 'updated_at');
        $now = now();

        foreach ($rows as $row) {
            $keys = [
                'exchange' => $exchange,
                'type' => $marketType,
                'symbol' => $dbSymbol,
                'timeframe' => $interval,
                'timestamp' => (int) $row['time'] * 1000,
            ];

            $values = [
                'open' => (float) $row['open'],
                'high' => (float) $row['high'],
                'low' => (float) $row['low'],
                'close' => (float) $row['close'],
                'volume' => (float) ($row['volume'] ?? 0),
            ];

            if ($hasCreatedAt) {
                $values['created_at'] = $now;
            }

            if ($hasUpdatedAt) {
                $values['updated_at'] = $now;
            }

            MarketCandle::query()->updateOrInsert($keys, $values);
        }
    }

    private function timeframeOptions(string $baseTf): array
    {
        return match (strtolower($baseTf)) {
            '1m' => ['1m'],
            '5m' => ['1m', '5m'],
            '10m' => ['1m', '5m', '10m'],
            '30m' => ['1m', '5m', '10m', '30m'],
            '1h' => ['1m', '5m', '10m', '30m', '1h'],
            '4h' => ['5m', '10m', '30m', '1h', '4h'],
            '1d' => ['10m', '30m', '1h', '4h', '1d'],
            '1w' => ['30m', '1h', '4h', '1d', '1w'],
            default => ['1m', '5m', '10m', '30m', '1h'],
        };
    }

    private function timeframeMs(string $timeframe): int
    {
        return match ($timeframe) {
            '1m' => 60_000,
            '5m' => 300_000,
            '10m' => 600_000,
            '30m' => 1_800_000,
            '1h' => 3_600_000,
            '4h' => 14_400_000,
            '1d' => 86_400_000,
            '1w' => 604_800_000,
            default => 3_600_000,
        };
    }

    private function bybitInterval(string $interval): string
    {
        return match ($interval) {
            '1m' => '1',
            '5m' => '5',
            '30m' => '30',
            '1h' => '60',
            '4h' => '240',
            '1d' => 'D',
            '1w' => 'W',
            default => '60',
        };
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

        return $symbol;
    }
}
