<?php

namespace App\Console\Commands;

use App\Jobs\CrawlMarketDataJob;
use App\Models\MarketCandle;
use App\Models\QcMethod;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchMarketCandleSync extends Command
{
    protected $signature = 'market-candles:sync
        {--active : Dispatch sync jobs for active qc_method rows}
        {--strategy= : Dispatch for one qc_method id}
        {--timeframe=auto : Candle timeframe to sync. Use "auto" for strategy-aware sync or "base" to use qc_method.tf}
        {--days=3 : Backfill window in days when no local candle exists}
        {--backfill : Always use the full --days window instead of only today/recent gap}
        {--repair-gaps : Dispatch extra jobs for internal candle gaps inside the recent --days window}
        {--max-gaps=3 : Maximum gap repair windows to dispatch per market}
        {--limit-pairs=0 : Optional max number of unique markets to dispatch}';

    protected $description = 'Dispatch queued jobs to keep market_candles warm for strategy charts.';

    public function handle(): int
    {
        $timeframe = $this->normalizeTimeframeOption((string) $this->option('timeframe'));
        $days = max(1, (int) $this->option('days'));
        $limitPairs = max(0, (int) $this->option('limit-pairs'));

        $methods = $this->methods();
        if ($methods->isEmpty()) {
            $this->warn('No strategies found for candle sync.');
            return self::SUCCESS;
        }

        $markets = $methods
            ->flatMap(fn($method) => $this->marketsFromMethod($method, $timeframe, $days))
            ->filter()
            ->values();

        if ($this->option('repair-gaps')) {
            $maxGaps = max(1, (int) $this->option('max-gaps'));
            $gapMarkets = $markets
                ->flatMap(fn($market) => $this->gapRepairMarkets($market, $days, $maxGaps))
                ->values();

            $markets = $markets->merge($gapMarkets);
        }

        $markets = $markets
            ->unique(fn($market) => implode('|', [
                $market['exchange'],
                $market['type'],
                $market['symbol'],
                $market['timeframe'],
                $market['start_date'],
                $market['end_date'],
            ]))
            ->values();

        if ($limitPairs > 0) {
            $markets = $markets->take($limitPairs);
        }

        $queued = 0;
        $skipped = 0;

        foreach ($markets as $market) {
            if ($this->pendingCrawlJobExists($market)) {
                $skipped++;
                $this->line(sprintf(
                    'Skipped existing %s %s %s %s %s -> %s',
                    $market['exchange'],
                    $market['type'],
                    $market['symbol'],
                    $market['timeframe'],
                    $market['start_date'],
                    $market['end_date'],
                ));
                continue;
            }

            CrawlMarketDataJob::dispatch(
                $market['exchange'],
                $market['type'],
                $market['symbol'],
                $market['timeframe'],
                $market['start_date'],
                $market['end_date'],
            );
            $queued++;

            $this->line(sprintf(
                'Queued %s %s %s %s %s -> %s',
                $market['exchange'],
                $market['type'],
                $market['symbol'],
                $market['timeframe'],
                $market['start_date'],
                $market['end_date'],
            ));
        }

        $this->info("Queued {$queued} market candle sync job(s); skipped {$skipped} duplicate pending job(s).");

        return self::SUCCESS;
    }

    private function pendingCrawlJobExists(array $market): bool
    {
        $expectedKey = $this->marketJobKey(
            $market['exchange'],
            $market['type'],
            $market['symbol'],
            $market['timeframe'],
            $market['start_date'],
            $market['end_date'],
        );

        return DB::table('jobs')
            ->where('payload', 'like', '%CrawlMarketDataJob%')
            ->get(['payload'])
            ->contains(function ($row) use ($expectedKey) {
                return $this->marketJobKeyFromPayload($row->payload) === $expectedKey;
            });
    }

    private function marketJobKeyFromPayload(string $payload): ?string
    {
        $decoded = json_decode($payload, true);
        $serializedJob = $decoded['data']['command'] ?? null;
        if (!$serializedJob) {
            return null;
        }

        $job = @unserialize($serializedJob);
        if (!$job instanceof CrawlMarketDataJob) {
            return null;
        }

        $reflection = new \ReflectionClass($job);
        $values = [];

        foreach (['exchange', 'type', 'symbol', 'timeframe', 'startDate', 'endDate'] as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $values[] = $property->getValue($job);
        }

        return $this->marketJobKey(...$values);
    }

    private function marketJobKey(string $exchange, string $type, string $symbol, string $timeframe, string $startDate, string $endDate): string
    {
        return strtolower(implode('|', [$exchange, $type, $symbol, $timeframe, $startDate, $endDate]));
    }

    private function methods()
    {
        if ($strategyId = $this->option('strategy')) {
            return QcMethod::query()
                ->where('id', $strategyId)
                ->get();
        }

        $query = QcMethod::query();

        if ($this->option('active')) {
            $query->where('onactive', 1);
        }

        return $query
            ->whereNotNull('pair')
            ->where('pair', '<>', '')
            ->get();
    }

    private function marketsFromMethod(QcMethod $method, string $timeframe, int $days)
    {
        $symbol = $this->slashSymbol($this->normalizeSymbol((string) $method->pair));
        if (!$symbol) {
            return collect();
        }

        $resolvedTimeframes = $this->timeframesForMethod($method, $timeframe);
        $exchange = str_contains(strtolower((string) $method->exchange), 'bybit') ? 'bybit' : 'binance';
        $type = $this->marketType($method);
        $end = Carbon::today();

        return collect($resolvedTimeframes)->map(function (string $resolvedTimeframe) use ($exchange, $type, $symbol, $days, $end) {
            $start = $this->option('backfill')
                ? Carbon::today()->subDays($days)
                : $this->startFromLocalGap($exchange, $type, $symbol, $resolvedTimeframe, $days);

            return [
                'exchange' => $exchange,
                'type' => $type,
                'symbol' => $symbol,
                'timeframe' => $resolvedTimeframe,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ];
        });
    }

    private function timeframesForMethod(QcMethod $method, string $timeframe): array
    {
        if ($timeframe !== 'auto') {
            return [$timeframe === 'base' ? $this->normalizeTimeframeOption((string) ($method->tf ?: '1h')) : $timeframe];
        }

        $baseTimeframe = $this->normalizeTimeframeOption((string) ($method->tf ?: '1h'));

        return match ($baseTimeframe) {
            // 10m is not a native Binance/Bybit interval in this app, so keep a 1m cache for aggregation.
            '10m' => ['1m'],
            default => [$baseTimeframe],
        };
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

    private function startFromLocalGap(string $exchange, string $type, string $symbol, string $timeframe, int $days): Carbon
    {
        $latestTimestamp = MarketCandle::query()
            ->where('exchange', $exchange)
            ->where('type', $type)
            ->where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->max('timestamp');

        if (!$latestTimestamp) {
            return Carbon::today()->subDays($days);
        }

        return Carbon::createFromTimestampMs((int) $latestTimestamp)
            ->subHours(3)
            ->startOfDay();
    }

    private function gapRepairMarkets(array $market, int $days, int $maxGaps)
    {
        $durationMs = MarketCandle::timeframeDurationMs($market['timeframe']);
        $fromMs = Carbon::today()->subDays($days)->getTimestampMs();
        $toMs = Carbon::today()->endOfDay()->getTimestampMs();

        $timestamps = MarketCandle::query()
            ->where('exchange', $market['exchange'])
            ->where('type', $market['type'])
            ->where('symbol', $market['symbol'])
            ->where('timeframe', $market['timeframe'])
            ->whereBetween('timestamp', [$fromMs, $toMs])
            ->orderBy('timestamp')
            ->pluck('timestamp')
            ->map(fn($timestamp) => (int) $timestamp)
            ->values();

        if ($timestamps->count() < 2) {
            return collect();
        }

        $repairs = collect();
        $previous = $timestamps->first();

        foreach ($timestamps->skip(1) as $timestamp) {
            $gapMs = $timestamp - $previous;

            if ($gapMs > ($durationMs * 2)) {
                $gapStart = Carbon::createFromTimestampMs($previous + $durationMs)->startOfDay();
                $gapEnd = Carbon::createFromTimestampMs($timestamp - $durationMs)->endOfDay();

                $repairs->push([
                    'exchange' => $market['exchange'],
                    'type' => $market['type'],
                    'symbol' => $market['symbol'],
                    'timeframe' => $market['timeframe'],
                    'start_date' => $gapStart->toDateString(),
                    'end_date' => $gapEnd->toDateString(),
                ]);

                if ($repairs->count() >= $maxGaps) {
                    break;
                }
            }

            $previous = $timestamp;
        }

        return $repairs;
    }

    private function normalizeTimeframeOption(string $timeframe): string
    {
        $timeframe = trim($timeframe);
        if ($timeframe === '') {
            return 'auto';
        }

        if ($timeframe === '1M') {
            return '1M';
        }

        return match (strtolower($timeframe)) {
            'auto' => 'auto',
            'base' => 'base',
            '1mo', '1mon', '1month' => '1M',
            default => strtolower($timeframe),
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

        return '';
    }
}
