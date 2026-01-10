# region imports
from AlgorithmImports import *
from TelegramNotifier import TelegramNotifier
from datetime import timedelta
# endregion

# pylint: disable=all
# type: ignore

class FuturesTradingStrategy(QCAlgorithm):
    """
    FUTURES trading strategy with Telegram notifications
    SMA crossover strategy with leverage and reminder before signal
    """

    def Initialize(self):
        self.SetStartDate(2024, 1, 1)
        self.qc_symbol = "BTCUSDT"
        self.leverage = 10  # 10x leverage for futures

        # =======================================
        # INITIALIZE TELEGRAM NOTIFIER
        # =======================================
        self.notifier = TelegramNotifier.init(
            algo=self,
            strategy_name="FUTURES SMA Crossover Strategy",
            market_type="FUTURES",
            webhook_url="https://sailing-royal-anna-investment.trycloudflare.com/api/quantconnect/webhook",
            webhook_token=None
        )

        # Asset
        self.crypto = self.AddCrypto(self.qc_symbol, Resolution.Minute).Symbol

        # Indicators
        self.fast = self.SMA(self.crypto, 10)
        self.slow = self.SMA(self.crypto, 30)
        self.SetWarmUp(30)

        # Track previous signal
        self.last_signal = "NONE"
        self.reminder_sent = False

        # Heartbeat
        self.Schedule.On(
            self.DateRules.EveryDay(),
            self.TimeRules.Every(timedelta(hours=6)),
            lambda: self.notifier.log("Futures strategy running normally...")
        )

    def GetSignal(self):
        if self.IsWarmingUp:
            return "NONE"
        if self.fast.Current.Value > self.slow.Current.Value:
            return "BUY"
        if self.fast.Current.Value < self.slow.Current.Value:
            return "SELL"
        return "NONE"

    def IsApproachingCrossover(self):
        if self.IsWarmingUp:
            return False
        fast_val = self.fast.Current.Value
        slow_val = self.slow.Current.Value
        diff_pct = abs(fast_val - slow_val) / slow_val * 100
        return diff_pct < 0.5

    def OnData(self, data):
        # Enable notifier on first run
        if not self.notifier.enabled:
            self.notifier.enable()

        if self.IsWarmingUp:
            return

        price = self.Securities[self.crypto].Price
        signal = self.GetSignal()

        # Send reminder when approaching crossover
        if self.IsApproachingCrossover() and not self.reminder_sent:
            if signal == "BUY":
                self.notifier.send_reminder(
                    self.qc_symbol,
                    f"⚠️ Prepare for LONG position - Fast SMA approaching crossover (Leverage: {self.leverage}x)"
                )
            elif signal == "SELL":
                self.notifier.send_reminder(
                    self.qc_symbol,
                    f"⚠️ Prepare for SHORT position - Fast SMA approaching crossover (Leverage: {self.leverage}x)"
                )
            self.reminder_sent = True

        # Send signal when crossover occurs
        if signal != self.last_signal and signal != "NONE":
            # Calculate TP and SL for futures (tighter stops due to leverage)
            # Example: 5% TP, 2% SL (with 10x leverage = 50% gain or 20% loss on margin)
            margin_usd = 100  # $100 margin per trade
            
            if signal == "BUY":
                tp = price * 1.05   # +5%
                sl = price * 0.98   # -2%
                qty = (margin_usd * self.leverage) / price  # Calculate position size
                
                self.notifier.send_signal(
                    symbol=self.qc_symbol,
                    side="BUY",
                    price=price,
                    tp=tp,
                    sl=sl,
                    leverage=self.leverage,
                    margin_usd=margin_usd,
                    qty=qty,
                    message=f"LONG signal - Fast SMA crossed above Slow SMA (Leverage: {self.leverage}x)"
                )
                
                # Execute trade (optional)
                self.SetHoldings(self.crypto, 0.95)
                
            elif signal == "SELL":
                tp = price * 0.95   # -5%
                sl = price * 1.02   # +2%
                qty = (margin_usd * self.leverage) / price
                
                self.notifier.send_signal(
                    symbol=self.qc_symbol,
                    side="SELL",
                    price=price,
                    tp=tp,
                    sl=sl,
                    leverage=self.leverage,
                    margin_usd=margin_usd,
                    qty=qty,
                    message=f"SHORT signal - Fast SMA crossed below Slow SMA (Leverage: {self.leverage}x)"
                )
                
                # Execute trade (optional)
                self.Liquidate(self.crypto)
            
            self.last_signal = signal
            self.reminder_sent = False
