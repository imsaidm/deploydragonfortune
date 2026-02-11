<?php

namespace App\Jobs;

use App\Models\TradingAccount;
use App\Models\Execution;
use App\Models\Position;
use App\Models\QcSignal;

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
    public function handle()
    {
        $signal = QcSignal::find($this->signalId);
        if (!$signal) {
            Log::error("AccountExecutionJob: Signal ID {$this->signalId} not found.");
            return;
        }

        // Resolve Exchange Service (Binance or Bybit)
        $exchange = \App\Services\ExchangeServiceFactory::make($this->account);

        $jenis = str_replace('-', '_', strtolower($signal->jenis)); // Normalize hyphens to underscores
    
    $method = $signal->method;
    $symbol = $method ? strtoupper(str_replace(['/', '-'], '', $method->pair)) : 'BTCUSDT';
    
    // Detection Logic: Strictly use market_type (futures/spot)
    $marketType = strtolower($signal->market_type ?: 'futures'); 
    $isFutures = ($marketType === 'futures');

    \Log::info("Trade Detection: Signal ID {$this->signalId} | MarketType: " . ($marketType ?: 'NULL') . " | Jenis: $jenis | Determined: " . ($isFutures ? 'FUTURES' : 'SPOT'));

    $side = 'long'; // Default
    $type = (str_contains($jenis, 'entry') || str_contains($jenis, 'buy')) ? 'entry' : 'exit';

    if ($isFutures) {
        $side = str_contains($jenis, 'long') ? 'long' : 'short';
    } else {
        $side = (str_contains($jenis, 'buy') || str_contains($jenis, 'long')) ? 'long' : 'long'; 
    }
    
    $orderSide = 'BUY';
    if ($isFutures) {
        if ($side === 'long') {
            $orderSide = ($type === 'entry') ? 'BUY' : 'SELL';
        } else {
            $orderSide = ($type === 'entry') ? 'SELL' : 'BUY';
        }
    } else {
        // Spot Logic (Simple BUY/SELL)
        $orderSide = ($type === 'entry') ? 'BUY' : 'SELL';
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
            \Log::info("AccountExecutionJob Logic Check: Signal ID {$this->signalId} | Type: $type | Jenis: $jenis | IsFutures: " . ($isFutures ? 'YES' : 'NO'));
            if ($type === 'exit') {
                // 1. Cleanup ALL pending orders first (SL/TP protection cleanup)
                $exchange->cancelAllSymbolOrders($symbol, $this->account, $this->signalId, $isFutures);
                
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
                    $actualBalance = $exchange->getSpecificAssetBalance($this->account, $baseAsset);
                    
                    \Log::info("Spot Exit Debug: Signal ID {$this->signalId} | DB Quantity: $closeQty | Actual Balance ($baseAsset): $actualBalance");

                    // For Spot, always flush 100% of the actual balance if it exists
                    if ($actualBalance > 0) {
                        $closeQty = $actualBalance;
                    }

                    // Floor to 4 decimal places to satisfy LOT_SIZE (stepSize 0.0001 for ETH/BTC)
                    $closeQty = floor($closeQty * 10000) / 10000;
                    \Log::info("Spot Exit Adjusted: Final Quantity after precision: $closeQty");
                }

                if ($closeQty <= 0) {
                    throw new \Exception("Exit failed: No active balance found for {$symbol} on Spot or recorded position is empty.");
                }

                $response = $exchange->closePosition($symbol, $orderSide, $closeQty, $this->account, $this->signalId, $isFutures);
                
                if (!$response->successful()) {
                    throw new \Exception("Exit order FAILED for {$symbol}. Response: " . $response->body());
                }
            } else {
                // 1. Set Leverage for Entry - ONLY FOR FUTURES
                if ($isFutures) {
                    $exchange->setLeverage($symbol, $leverage, $this->account, $this->signalId);
                }

                // 2. Execute Market Entry Order
                $balance = $exchange->getBalance($this->account);
                
                // Select balance based on market type
                $usdtBalance = $isFutures ? ($balance['available_balance'] ?? 0) : ($balance['spot'] ?? 0);
                
                // Use Signal Ratio (Allocation %) from QuantConnect
                // If signal->ratio is 0.1, it means use 10% of available balance
                $ratio = (float) ($signal->ratio ?: 0.1); 
                $price = (float) ($signal->price_entry ?: $signal->price_exit ?: 1);

                // Leverage is 1 for Spot, and N for Futures. 
                // targetPositionValue is the total value in USD after leverage.
                $targetPositionValue = ($usdtBalance * $ratio) * $leverage;
                
                // Binance Min Order is generally $5-10. $6 is our safety base.
                $minOrderUsdt = 6;
                
                if ($targetPositionValue < $minOrderUsdt && ($usdtBalance * $leverage) >= $minOrderUsdt) {
                    $targetPositionValue = $minOrderUsdt;
                }

                // If even after that it's too small, try to use EVERYTHING if total balance is > $minOrderUsdt
                if ($targetPositionValue < $minOrderUsdt && $usdtBalance >= 5.5) {
                    $targetPositionValue = $usdtBalance - 0.1; // Room for fees
                }

                $quantity = $targetPositionValue / $price;

                // Precision: Spot major pairs usually 4 decimals. Futures use 3 for ETH.
                $precision = $isFutures ? 1000 : 10000;
                $quantity = floor($quantity * $precision) / $precision;

                $finalValue = $quantity * $price;
                if ($quantity <= 0 || $finalValue < 5) {
                    throw new \Exception("Quantity too small or insufficient balance. Target: $" . number_format($finalValue, 2) . " (Min $5)");
                }

                $response = $exchange->placeMarketOrder($symbol, $orderSide, $quantity, $this->account, $this->signalId, $isFutures);
                
                // 3. Attach Protection (SL & TP) - ONLY FOR FUTURES
                if ($response->successful() && $isFutures) {
                    usleep(1500000); // Wait 1.5s for position to settle
                    $markPrice = $exchange->getMarkPrice($symbol, $isFutures);

                    if (!$markPrice) {
                        \Log::error("Failed to fetch mark price for Signal ID {$this->signalId}. Skipping protection.");
                        return;
                    }

                    $protQty = $quantity;

                    // 1. Place SL (Stop Loss)
                    if ($signal->target_sl) {
                        $slPrice = (float)$signal->target_sl;
                        $slSide = ($orderSide === 'BUY' || $orderSide === 'LONG') ? 'SELL' : 'BUY';

                        if (($orderSide === 'BUY' && $slPrice < $markPrice) || 
                            ($orderSide === 'SELL' && $slPrice > $markPrice)) {
                            $exchange->placeStopMarketOrder($symbol, $slSide, $slPrice, $protQty, $this->account, $this->signalId, $isFutures);
                            usleep(500000); // 0.5s between SL and TP
                        } else {
                            \Log::warning("Skipped SL for Signal ID {$this->signalId}: Price {$slPrice} invalid relative to Mark Price {$markPrice}.");
                        }
                    }

                    // 2. Place TP (Take Profit)
                    if ($signal->target_tp) {
                        $tpPrice = (float)$signal->target_tp;
                        $tpSide = ($orderSide === 'BUY' || $orderSide === 'LONG') ? 'SELL' : 'BUY';

                        if (($orderSide === 'BUY' && $tpPrice > $markPrice) || 
                            ($orderSide === 'SELL' && $tpPrice < $markPrice)) {
                            $exchange->placeTakeProfitMarketOrder($symbol, $tpSide, $tpPrice, $protQty, $this->account, $this->signalId, $isFutures);
                        } else {
                            \Log::warning("Skipped TP for Signal ID {$this->signalId}: Price {$tpPrice} invalid relative to Mark Price {$markPrice}.");
                        }
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
                throw new \Exception(ucfirst($this->account->exchange) . " Order Failed: " . $response->body());
            } else {
                throw new \Exception(ucfirst($this->account->exchange) . " order response is null.");
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
