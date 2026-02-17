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
    
    // Detection Logic: Default to Spot if field is missing for safety
    $marketType = strtolower($signal->market_type ?: 'spot'); 
    $isFutures = ($marketType === 'futures');

    \Log::info("Trade Detection: Signal ID {$this->signalId} | MarketType: " . ($signal->market_type ?: 'FIELD_MISSING') . " | Determined: " . ($isFutures ? 'FUTURES' : 'SPOT'));

    $side = 'long'; // Default
    
    // Use signal type if available, otherwise derive from jenis
    $type = strtolower($signal->type ?: '');
    if (!in_array($type, ['entry', 'exit'])) {
        $type = (str_contains($jenis, 'entry') || str_contains($jenis, 'buy')) ? 'entry' : 'exit';
    }

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
            $accName = $this->account->account_name;
            $accExchange = strtoupper($this->account->exchange);
            \Log::info(">>> PROCESSING ACCOUNT: [ID: {$this->account->id}] {$accName} ({$accExchange}) for Signal ID {$this->signalId}");

                // Define price early for executions record
                $price = (float) ($signal->price_entry ?: $signal->price_exit ?: 1);

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
                    $actualBalance = $exchange->getSpecificAssetBalance($this->account, $baseAsset, $this->signalId);
                    
                    // For Spot, always flush 100% of the actual balance if it exists
                    if ($actualBalance > 0) {
                        $closeQty = $actualBalance;
                    }

                    // Precision: 5 decimals for BTC, 4 for ETH (safe for Spot)
                    $prec = str_contains($symbol, 'BTC') ? 100000 : 10000;
                    $closeQty = floor($closeQty * $prec) / $prec;
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
                // Fetch proportional sizing based on $105 base capital
                $sizing = $exchange->calculateProportionalQuantity(
                    $masterQuantity, $this->account, $symbol, $isFutures, $this->signalId
                );
                
                $quantity = $sizing['quantity'];
                $usdtBalance = $sizing['balance'];
                
                // Fetch real mark price for accurate notion validation
                $realMarkPrice = $exchange->getMarkPrice($symbol, $isFutures);
                $effectivePrice = $realMarkPrice ?: $price;

                if ($effectivePrice <= 0) {
                     throw new \Exception("Order aborted: Effective price is 0 for Signal ID {$this->signalId}.");
                }

                // Safety: Binance/Bybit Min Order is generally $5, but some pairs (ETHUSDT) require $20.
                // We bump to $22.00 (buffer over $20) if balance allows but calculated qty is too small.
                $minOrderUsdt = 21.00; 
                $calculatedValue = $quantity * $effectivePrice;
                
                if ($calculatedValue < $minOrderUsdt && ($usdtBalance * $leverage) >= $minOrderUsdt) {
                    // Bump to $22.00 to ensure we stay above $20 even after truncation
                    $targetValue = 22.00;
                    $rawQty = $targetValue / $effectivePrice;
                    
                    // Simple "Ceil to 3 decimals" to avoid truncation dropping us below 20
                    // Most perp pairs have 3 decimals (0.001) or 2 (0.01)
                    $quantity = ceil($rawQty * 1000) / 1000; 
                    
                    \Log::info("Min-Order Safety Bump: Signal ID {$this->signalId} | Qty boosted to {$quantity} (~\$22.00) for " . strtoupper($this->account->exchange));
                }

                if ($quantity <= 0) {
                    throw new \Exception("Quantity calculation yielded 0. Balance: $" . number_format($usdtBalance, 2) . " | Price: $effectivePrice");
                }

                $actualQty = $quantity;
                if (in_array($this->account->exchange, ['bybit', 'binance']) && !$isFutures && $orderSide === 'BUY') {
                    // For Spot Market BUY, qty passed to wrapper can be treated as USDT if >= 5
                    $actualQty = $quantity * $price;
                    // Note: Floor for USDT common safety
                    $actualQty = floor($actualQty * 100) / 100;
                    \Log::info("Spot Market Buy Adjustment: Signal ID {$this->signalId} | Using USDT Amount: $actualQty");
                }

                $response = $exchange->placeMarketOrder($symbol, $orderSide, $actualQty, $this->account, $this->signalId, $isFutures);
                
                /*
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
                */
            }

            $orderData = $response ? $response->json() : null;
            $isExchangeSuccess = $response && $response->successful();
            
            // Bybit specific check: Even if 200 OK, check retCode
            if ($isExchangeSuccess && $this->account->exchange === 'bybit') {
                if (isset($orderData['retCode']) && $orderData['retCode'] !== 0) {
                    $isExchangeSuccess = false;
                }
            }

            if ($isExchangeSuccess) {
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
                $errorMsg = $response->body();
                if ($this->account->exchange === 'bybit' && isset($orderData['retMsg'])) {
                    $errorMsg = $orderData['retMsg'] . " (Code: " . $orderData['retCode'] . ")";
                }
                throw new \Exception(ucfirst($this->account->exchange) . " Order Failed: " . $errorMsg);
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
