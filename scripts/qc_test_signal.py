# ====================================================================
# DRAGON FORTUNE - TEST SIGNAL (COPY INI KE QUANTCONNECT)
# ====================================================================
# Kirim 3 signal: ALERT -> ENTRY -> EXIT
# Langsung terkirim saat deploy, tidak perlu tunggu market!
# ====================================================================

from AlgorithmImports import *
from datetime import datetime
import json


class DragonFortuneTest(QCAlgorithm):
    
    def initialize(self):
        self.set_cash(100000)
        
        # WEBHOOK URL
        self.webhook_url = "https://advisors-litigation-suited-wiki.trycloudflare.com/api/quantconnect/signals/webhook"
        self.qc_project_id = self.project_id
        self.qc_project_name = "Dragon Fortune Test"
        
        self.debug("=" * 60)
        self.debug("[TEST] Sending 3 test signals NOW...")
        self.debug("=" * 60)
        
        # Langsung kirim test signals
        self.send_test_signals()
    
    def send_test_signals(self):
        btc_price = 95000.00
        tp = 96900.00
        sl = 93100.00
        
        # 1. ALERT
        self.send_webhook({
            "type": "ALERT",
            "jenis": "LONG",
            "symbol": "BTCUSD",
            "price": btc_price,
            "quantity": 0.1,
            "target_tp": tp,
            "target_sl": sl,
            "message": "RSI Oversold + EMA Crossover forming. Preparing LONG...",
            "project_id": self.qc_project_id,
            "algorithm_name": self.qc_project_name,
            "source": "quantconnect_test"
        })
        self.debug("[SENT] 1/3 - ALERT LONG")
        
        # 2. ENTRY
        entry_price = btc_price * 1.001
        self.send_webhook({
            "type": "ENTRY",
            "jenis": "LONG",
            "symbol": "BTCUSD",
            "price": entry_price,
            "quantity": 0.1,
            "target_tp": tp,
            "target_sl": sl,
            "message": "[BUY] LONG Entry",
            "project_id": self.qc_project_id,
            "algorithm_name": self.qc_project_name,
            "source": "quantconnect_test"
        })
        self.debug("[SENT] 2/3 - ENTRY LONG")
        
        # 3. EXIT
        exit_price = tp * 0.99
        pnl = (exit_price - entry_price) * 0.1
        self.send_webhook({
            "type": "EXIT",
            "jenis": "LONG",
            "symbol": "BTCUSD",
            "price": exit_price,
            "quantity": 0.1,
            "target_tp": 0,
            "target_sl": 0,
            "realized_pnl": pnl,
            "message": "Take Profit Hit!",
            "project_id": self.qc_project_id,
            "algorithm_name": self.qc_project_name,
            "source": "quantconnect_test"
        })
        self.debug("[SENT] 3/3 - EXIT LONG")
        
        self.debug("=" * 60)
        self.debug("[SUCCESS] All 3 signals sent!")
        self.debug("Check Telegram for: ALERT, ENTRY, EXIT")
        self.debug("=" * 60)
    
    def send_webhook(self, payload):
        try:
            self.notify.web(self.webhook_url, json.dumps(payload))
            return True
        except Exception as e:
            self.error(f"[ERROR] {str(e)}")
            return False
    
    def on_data(self, data):
        pass
