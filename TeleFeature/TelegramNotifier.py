# region imports
from AlgorithmImports import *
import json
import re
# endregion

class TelegramNotifier:
    """
    TelegramNotifier - Send trading signals and reminders to Telegram via webhook
    
    Usage in your main.py:
        from TelegramNotifier import TelegramNotifier
        
        self.notifier = TelegramNotifier.init(
            algo=self,
            strategy_name="My Strategy Name",
            market_type="FUTURES",  # or "SPOT"
            webhook_url="https://your-domain.com/api/quantconnect/webhook"
        )
    """
    
    @staticmethod
    def init(algo, strategy_name, market_type, webhook_url, webhook_token=None):
        """Initialize TelegramNotifier"""
        notifier = TelegramNotifier(
            algo=algo,
            strategy_name=strategy_name,
            market_type=market_type,
            webhook_url=webhook_url,
            webhook_token=webhook_token
        )
        algo.Debug(f"[TelegramNotifier] Initialized for {market_type} trading")
        return notifier

    def __init__(self, algo, strategy_name, market_type, webhook_url, webhook_token=None):
        self.algo = algo
        self.strategy_name_raw = strategy_name
        self.strategy_name = self.slugify(strategy_name)
        self.market_type = market_type.upper()
        self.webhook_url = webhook_url.rstrip("/")
        self.webhook_token = webhook_token
        self.qc_id = None
        self.enabled = False

        if self.market_type not in ["SPOT", "FUTURES"]:
            raise ValueError("market_type must be 'SPOT' or 'FUTURES'")

    def slugify(self, text):
        """Convert text to URL-friendly slug"""
        text = text.lower()
        text = re.sub(r"[^a-z0-9]+", "-", text)
        return re.sub(r"-+", "-", text).strip("-")

    def generate_qc_id(self):
        """Generate unique QuantConnect ID for this algorithm run"""
        try:
            algo_id = self.algo.AlgorithmId
            timestamp = self.algo.Time.strftime("%Y%m%d%H%M%S")
            self.qc_id = f"{algo_id}_{timestamp}"
            self.log(f"QC_ID generated: {self.qc_id}")
        except Exception as e:
            self.algo.Debug(f"[NOTIFIER ERROR] QC_ID generation failed: {e}")
            self.qc_id = "unknown"

    def enable(self):
        """Enable notifications"""
        if self.qc_id is None:
            self.generate_qc_id()
        
        self.enabled = True
        self.log("Telegram notifications ENABLED")

    def post(self, endpoint, payload):
        """Send POST request to webhook"""
        url = f"{self.webhook_url}/{endpoint}"
        
        try:
            if self.webhook_token:
                payload["token"] = self.webhook_token
            
            self.algo.Notify.Web(url, json.dumps(payload))
            self.algo.Debug(f"[NOTIFIER] Sent to {endpoint}")
        except Exception as e:
            self.algo.Debug(f"[NOTIFIER ERROR] Webhook failed: {e}")

    def log(self, msg, level="INFO"):
        """Log message to QuantConnect console"""
        log_text = f"[{level}] {self.algo.Time} | {msg}"
        
        if level == "ERROR":
            self.algo.Error(log_text)
        else:
            self.algo.Debug(log_text)

    def send_reminder(self, symbol, message):
        """Send reminder notification"""
        if not self.enabled:
            self.log(f"[OFFLINE] Reminder prepared: {symbol} - {message}", "INFO")
            return

        if self.qc_id is None:
            self.generate_qc_id()

        payload = {
            "qc_id": self.qc_id,
            "market_type": self.market_type,
            "symbol": symbol,
            "message": message,
        }

        self.post("reminder", payload)
        self.log(f"Reminder sent: {symbol} - {message}", "REMINDER")

    def send_signal(self, symbol, side, price, tp, sl, leverage=None, margin_usd=None, qty=None, message=None):
        """Send trading signal"""
        if not self.enabled:
            self.log(f"[OFFLINE] Signal prepared: {side} {symbol} @ {price}", "TRADE")
            return

        if self.qc_id is None:
            self.generate_qc_id()

        if message is None:
            message = f"{side} signal triggered - {self.strategy_name_raw}"

        payload = {
            "qc_id": self.qc_id,
            "market_type": self.market_type,
            "symbol": symbol,
            "side": side.upper(),
            "price": float(price),
            "tp": float(tp),
            "sl": float(sl),
            "message": message,
        }

        if leverage is not None:
            payload["leverage"] = int(leverage)
        if margin_usd is not None:
            payload["margin_usd"] = float(margin_usd)
        if qty is not None:
            payload["quantity"] = float(qty)

        self.post("signal", payload)
        self.log(f"Signal sent: {side} {symbol} @ {price} | TP: {tp} | SL: {sl}", "TRADE")
