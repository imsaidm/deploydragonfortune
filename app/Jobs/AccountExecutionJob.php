<?php

namespace App\Jobs;

use App\Models\TradingAccount;
use App\Models\Execution;
use App\Models\Position;
use App\Models\QcSignal;
use App\Services\BinanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AccountExecutionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $account;
    protected $signalId;
    protected $strategyId;

    /**
     * Create a new job instance.
     */
    public function __construct(TradingAccount $account, int $signalId, $strategyId)
    {
        $this->account = $account;
        $this->signalId = $signalId;
        $this->strategyId = $strategyId;
    }

    /**
     * Execute the job.
     */
    public function handle(BinanceService $binance)
    {
        $signal = QcSignal::find($this->signalId);
        if (!$signal) {
            Log::error("AccountExecutionJob: Signal ID {$this->signalId} not found.");
            return;
        }

        $jenis = str_replace('-', '_', strtolower($signal->jenis)); // Normalize hyphens to underscores
    
    $method = $signal->method;
    $symbol = $method ? strtoupper(str_replace(['/', '-'], '', $method->pair)) : 'BTCUSDT';
    
    // Detection Logic: Strictly use market_type (futures/spot)
    $marketType = strtolower($signal->market_type ?: 'futures'); 
    $isFutures = ($marketType === 'futures');

    \Log::info("Trade Detection: Signal ID {$this->signalId} | MarketType: " . ($marketType ?: 'NULL') . " | Jenis: $jenis | Determined: " . ($isFutures ? 'FUTURES' : 'SPOT'));

    $side = 'long'; // Default
    $type = str_contains($jenis, 'entry') ? 'entry' : 'exit';

    if ($isFutures) {
        $side = str_contains($jenis, 'long') ? 'long' : 'short';
    } else {
        $side = str_contains($jenis, 'buy') ? 'long' : 'long'; // Spot is usually 'BUY' as entry
    }
    
    $binanceSide = 'BUY';
    if ($isFutures) {
        if ($side === 'long') {
            $binanceSide = ($type === 'entry') ? 'BUY' : 'SELL';
        } else {
            $binanceSide = ($type === 'entry') ? 'SELL' : 'BUY';
        }
    } else {
        // Spot Logic (Simple BUY/SELL)
        $binanceSide = ($type === 'entry') ? 'BUY' : 'SELL';
    }

        $leverage = (int) ($signal->leverage ?: 1);
        $masterQuantity = (float) ($signal->quantity ?: 0);

        // executions.status ENUM: pending, success, failed, retrying
        $execution = Execution::create([
            'qc_signal_id' => $signal->id,
            'strategy_id' => $this->strategyId,
            'account_id' => $this->account->id,
            'symbol' => $symbol,
            'side' => $side,
            'type' => $type,
            'status' => 'pending',
            'master_quantity' => $masterQuantity,
            'follower_quantity' => 0, // Mandatory in DB
            'executed_price' => 0,    // Mandatory in DB
            'leverage' => $leverage,
        ]);

        try {
            if ($type === 'exit') {
                // 1. Cleanup ALL pending orders first (SL/TP protection cleanup) - ONLY FOR FUTURES
                if ($isFutures) {
                    $binance->cancelAllSymbolOrders($symbol, $this->account, $this->signalId);
                }
                
                // 2. Execute Market Exit Order
                $pos = Position::where('account_id', $this->account->id)
                               ->where('symbol', $symbol)
                               ->where('status', 'active')
                               ->first();
                
                $closeQty = $pos ? (float)$pos->quantity : $masterQuantity;
                
                // For Spot, we must check if we actually have enough of the base asset 
                // because fees are deducted from the coin itself during buy.
                if (!$isFutures) {
                    $baseAsset = str_replace(['USDT', '/'], '', $symbol);
                    $actualBalance = $binance->getSpecificAssetBalance($this->account, $baseAsset);
                    
                    \Log::info("Spot Exit Debug: Signal ID {$this->signalId} | DB Quantity: $closeQty | Actual Balance ($baseAsset): $actualBalance");

                    // If we have less than we thought (due to fees), use the actual balance
                    if ($actualBalance < $closeQty) {
                        $closeQty = $actualBalance;
                    }

                    // Floor to 4 decimal places to satisfy LOT_SIZE (stepSize 0.0001 for ETH/BTC)
                    $closeQty = floor($closeQty * 10000) / 10000;
                    \Log::info("Spot Exit Adjusted: Final Quantity after precision: $closeQty");
                }

                if ($closeQty <= 0) {
                    throw new \Exception("Exit failed: No active balance found for {$symbol} on Spot or recorded position is empty.");
                }

                $response = $binance->closePosition($symbol, $binanceSide, $closeQty, $this->account, $this->signalId, $isFutures);
                
                if (!$response->successful()) {
                    throw new \Exception("Exit order FAILED for {$symbol}. Response: " . $response->body());
                }
            } else {
                // 1. Set Leverage for Entry - ONLY FOR FUTURES
                if ($isFutures) {
                    $binance->setLeverage($symbol, $leverage, $this->account, $this->signalId);
                }

                // 2. Execute Market Entry Order
                $balance = $binance->getBalance($this->account);
                
                // Select balance based on market type
                $usdtBalance = $isFutures ? ($balance['available_balance'] ?? 0) : ($balance['spot'] ?? 0);
                
                // Use Signal Ratio (Allocation %) from QuantConnect
                // If signal->ratio is 0.1, it means use 10% of available balance
                $ratio = (float) ($signal->ratio ?: 0.1); 
                $price = (float) ($signal->price_entry ?: $signal->price_exit ?: 1);

                // Leverage is 1 for Spot, and N for Futures. 
                // targetPositionValue is the total value in USD after leverage.
                $targetPositionValue = ($usdtBalance * $ratio) * $leverage;
                
                if ($targetPositionValue < 6 && ($usdtBalance * $leverage) >= 6) {
                    $targetPositionValue = 6;
                }

                $quantity = $targetPositionValue / $price;
                $quantity = floor($quantity * 1000) / 1000;

                if ($quantity <= 0 || ($quantity * $price) < 5) {
                    throw new \Exception("Quantity too small or insufficient balance. Target Value: $" . ($quantity * $price));
                }

                $response = $binance->placeMarketOrder($symbol, $binanceSide, $quantity, $this->account, $this->signalId, $isFutures);
                
                // 3. Attach Protection (SL/TP) if market order was successful - ONLY FOR FUTURES
                if ($isFutures && $response->successful()) {
                    $orderData = $response->json();
                    
                    // Place Stop Loss if target_sl is present
                    if ($signal->target_sl) {
                        $slSide = ($binanceSide === 'BUY') ? 'SELL' : 'BUY';
                        $binance->placeStopMarketOrder($symbol, $slSide, (float)$signal->target_sl, $quantity, $this->account, $this->signalId);
                    }
                    
                    // Place Take Profit if target_tp is present
                    if ($signal->target_tp) {
                        $tpSide = ($binanceSide === 'BUY') ? 'SELL' : 'BUY';
                        $binance->placeTakeProfitMarketOrder($symbol, $tpSide, (float)$signal->target_tp, $quantity, $this->account, $this->signalId);
                    }
                }
            }

            if ($response && $response->successful()) {
                $orderData = $response->json();
                
                $execution->update([
                    'status' => 'success',
                    'follower_quantity' => ($type === 'exit' ? ($closeQty ?? 0) : $quantity),
                    'executed_price' => $orderData['avgPrice'] ?? ($orderData['price'] ?? $price),
                    'executed_at' => now()
                ]);

                if ($type === 'entry') {
                    Position::updateOrCreate(
                        ['account_id' => $this->account->id, 'symbol' => $symbol],
                        [
                            'strategy_id' => $this->strategyId,
                            'side' => $side,
                            'quantity' => $quantity,
                            'entry_price' => $orderData['avgPrice'] ?? $price,
                            'leverage' => $leverage,
                            'status' => 'active',
                            'opened_at' => now()
                        ]
                    );
                } else {
                    Position::where('account_id', $this->account->id)
                            ->where('symbol', $symbol)
                            ->update([
                                'status' => 'closed',
                                'closed_at' => now()
                            ]);
                }
            } elseif ($response) {
                throw new \Exception("Binance Order Failed: " . $response->body());
            } else {
                throw new \Exception("Binance order response is null.");
            }

        } catch (\Exception $e) {
            Log::error("QC Execution Failed for {$this->account->account_name}: " . $e->getMessage());
            $execution->update([
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 255)
            ]);
        }
    }
}
