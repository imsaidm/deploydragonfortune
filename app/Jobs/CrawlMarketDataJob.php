<?php

namespace App\Jobs;

use App\Models\MarketCandle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CrawlMarketDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 3600;

    public function __construct(
        protected string $exchange,
        protected string $type,
        protected string $symbol,
        protected string $timeframe,
        protected string $startDate,
        protected string $endDate,
    ) {}

    public function handle(): void
    {
        $startMs    = (int) (strtotime($this->startDate) * 1000);
        $endMs      = (int) (strtotime($this->endDate . ' 23:59:59') * 1000);
        $stepMs     = MarketCandle::timeframeDurationMs($this->timeframe);
        $since      = $startMs;
        $totalSaved = 0;
        $limit      = 1000;

        Log::info('[CrawlMarketDataJob] START', [
            'exchange'  => $this->exchange,
            'type'      => $this->type,
            'symbol'    => $this->symbol,
            'timeframe' => $this->timeframe,
            'start'     => $this->startDate,
            'end'       => $this->endDate,
        ]);

        $ccxt = $this->buildCcxtExchange();

        // loadMarkets() is required so CCXT registers all valid symbols before fetchOHLCV
        $ccxt->loadMarkets();

        // CCXT v1.53: use standard 'BTC/USDT' format for all exchanges
        $symbol = $this->symbol;

        do {
            // Simple rate limit: 500ms sleep = max 2 requests/second (no Redis needed)
            usleep(500_000);

            try {
                $params = [];
                if ($this->exchange === 'bybit' && $this->type === 'future') {
                    $params['category'] = 'linear';
                }

                $candles = $ccxt->fetchOHLCV(
                    $symbol,
                    $this->timeframe,
                    $since,
                    $limit,
                    $params
                );
            } catch (\Exception $e) {
                Log::error('[CrawlMarketDataJob] fetchOHLCV error: ' . $e->getMessage());
                $since += $stepMs * $limit;
                continue;
            }

            if (empty($candles)) {
                break;
            }

            $rows = [];
            $now  = now()->toDateTimeString();

            foreach ($candles as [$ts, $open, $high, $low, $close, $volume]) {
                if ($ts > $endMs) break;
                $rows[] = [
                    'exchange'   => $this->exchange,
                    'type'       => $this->type,
                    'symbol'     => $this->symbol,
                    'timeframe'  => $this->timeframe,
                    'timestamp'  => $ts,
                    'open'       => $open,
                    'high'       => $high,
                    'low'        => $low,
                    'close'      => $close,
                    'volume'     => $volume,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                MarketCandle::upsert(
                    $rows,
                    ['exchange', 'type', 'symbol', 'timeframe', 'timestamp'],
                    ['open', 'high', 'low', 'close', 'volume', 'updated_at']
                );
                $totalSaved += count($rows);
            }

            $lastTs = end($candles)[0];
            $since  = $lastTs + $stepMs;

            Log::info('[CrawlMarketDataJob] batch', [
                'saved' => count($rows),
                'total' => $totalSaved,
                'next'  => $since,
            ]);

        } while ($since <= $endMs);

        Log::info('[CrawlMarketDataJob] DONE', [
            'total_saved' => $totalSaved,
        ]);
    }

    private function buildCcxtExchange(): \ccxt\Exchange
    {
        $opts = ['enableRateLimit' => true];

        if ($this->exchange === 'binance') {
            $opts['options'] = [
                'defaultType' => $this->type === 'future' ? 'future' : 'spot',
            ];
            return new \ccxt\binance($opts);
        }

        return match ($this->exchange) {
            'bybit'   => new \ccxt\bybit($opts),
            default   => throw new \InvalidArgumentException("Unsupported exchange: {$this->exchange}"),
        };
    }
}
