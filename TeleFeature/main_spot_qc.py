# region imports
from AlgorithmImports import *
from TelegramNotifier import TelegramNotifier
from datetime import timedelta
# endregion

# pylint: disable=all
# type: ignore

class SpotTradingStrategy(QCAlgorithm):
    """
    SPOT trading strategy with Telegram notifications
    SMA crossover strategy with reminder before signal
    """

    def Initialize(self):
        self.SetStartDate(2024, 1, 1)
        self.qc_symbol = "BTCUSDT"

        # =======================================
        # INITIALIZE TELEGRAM NOTIFIER
        # =======================================
        self.notifier = TelegramNotifier.init(
            algo=self,
            strategy_name="SPOT SMA Crossover Strategy",
            market_type="SPOT",
            webhook_url="https://sailing-royal-anna-investment.trycloudflare.com/api/quantconnect/webhook",
            webhook_token=None  # Optional, for security
        )

        # Asset
        self.crypto = self.AddCrypto(self.qc_symbol, Resolution.Minute).Symbol

        # Indicators
        self.fast = self.SMA(self.crypto, 10)
        self.slow = self.SMA(self.crypto, 30)
        self.SetWarmUp(30)

        # Track previous signal to avoid duplicates
        self.last_signal = "NONE"
        self.reminder_sent = False

        # Heartbeat (optional - for monitoring)
        self.Schedule.On(
            self.DateRules.EveryDay(),
            self.TimeRules.Every(timedelta(hours=6)),
            lambda: self.notifier.log("Strategy running normally...")
        )

    def GetSignal(self):
        """Determine trading signal based on SMA crossover"""
        if self.IsWarmingUp:
            return "NONE"
        
        if self.fast.Current.Value > self.slow.Current.Value:
            return "BUY"
        if self.fast.Current.Value < self.slow.Current.Value:
            return "SELL"
        
        return "NONE"

    def IsApproachingCrossover(self):
        """Check if SMAs are approaching crossover (within 0.5%)"""
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
                    "⚠️ Prepare for BUY signal - Fast SMA approaching crossover above Slow SMA"
                )
            elif signal == "SELL":
                self.notifier.send_reminder(
                    self.qc_symbol,
                    "⚠️ Prepare for SELL signal - Fast SMA approaching crossover below Slow SMA"
                )
            self.reminder_sent = True

        # Send signal when crossover occurs
        if signal != self.last_signal and signal != "NONE":
            # Calculate TP and SL (example: 2.5% TP, 1.5% SL)
            if signal == "BUY":
                tp = price * 1.025  # +2.5%
                sl = price * 0.985  # -1.5%
                
                self.notifier.send_signal(
                    symbol=self.qc_symbol,
                    side="BUY",
                    price=price,
                    tp=tp,
                    sl=sl,
                    qty=0.01,  # Example quantity
                    message="BUY signal triggered - Fast SMA crossed above Slow SMA"
                )
                
                # Execute trade (optional)
                self.SetHoldings(self.crypto, 0.95)
                
            elif signal == "SELL":
                tp = price * 0.975  # -2.5%
                sl = price * 1.015  # +1.5%
                
                self.notifier.send_signal(
                    symbol=self.qc_symbol,
                    side="SELL",
                    price=price,
                    tp=tp,
                    sl=sl,
                    qty=0.01,
                    message="SELL signal triggered - Fast SMA crossed below Slow SMA"
                )
                
                # Execute trade (optional)
                self.Liquidate(self.crypto)
            
            self.last_signal = signal
            self.reminder_sent = False  # Reset reminder flag
