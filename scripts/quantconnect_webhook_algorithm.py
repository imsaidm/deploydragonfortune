# ====================================================================
# DRAGON FORTUNE - QUANTCONNECT WEBHOOK ALGORITHM
# ====================================================================
# 
# CARA PAKAI:
# 1. Login ke QuantConnect (quantconnect.com)
# 2. Buat project baru atau edit existing
# 3. HAPUS semua code di main.py
# 4. PASTE semua code ini
# 5. GANTI webhook_url dengan URL tunnel kamu
# 6. Deploy sebagai "Paper Trading" atau "Live Trading"
#
# SIGNAL TYPES:
# - ALERT  → Reminder/Heads up sebelum entry
# - ENTRY  → Signal masuk posisi (BUY/SELL)
# - EXIT   → Signal keluar posisi dengan PnL
#
# ====================================================================

from AlgorithmImports import *
from datetime import datetime
import json


class DragonFortuneWebhook(QCAlgorithm):
    """
    Dragon Fortune Signal Manager - QuantConnect Integration
    
    Signal flow: ALERT → ENTRY → EXIT
    Kirim notifikasi ke Telegram via Laravel webhook
    """
    
    def initialize(self):
        """Setup algorithm"""
        # ============================================================
        # KONFIGURASI - GANTI SESUAI KEBUTUHAN
        # ============================================================
        
        # Webhook URL - Dragon Fortune Signal Manager
        self.webhook_url = "https://advisors-litigation-suited-wiki.trycloudflare.com/api/quantconnect/signals/webhook"
        
        # Project Info
        self.qc_project_id = self.project_id  # Auto dari QuantConnect
        self.qc_project_name = "Dragon Fortune Algorithm"
        
        # Trading Settings
        self.set_start_date(2026, 1, 1)
        self.set_cash(100000)
        
        # Add symbols
        self.btc = self.add_crypto("BTCUSD", Resolution.MINUTE).symbol
        self.eth = self.add_crypto("ETHUSD", Resolution.MINUTE).symbol
        
        # Position tracking
        self.positions = {}  # Track open positions
        self.last_alert = {}  # Track last alert per symbol
        
        # Indicators (contoh: RSI untuk signal)
        self.rsi_btc = self.rsi(self.btc, 14, Resolution.HOUR)
        self.rsi_eth = self.rsi(self.eth, 14, Resolution.HOUR)
        
        self.debug("=" * 60)
        self.debug("[DRAGON FORTUNE] Algorithm initialized!")
        self.debug(f"[WEBHOOK] {self.webhook_url}")
        self.debug("=" * 60)
    
    # ================================================================
    # HELPER: KIRIM SIGNAL KE WEBHOOK
    # ================================================================
    
    def send_signal(self, signal_type: str, action: str, symbol: str, 
                    price: float, quantity: float = 0,
                    target_tp: float = 0, target_sl: float = 0,
                    realized_pnl: float = None, message: str = ""):
        """
        Kirim signal ke Dragon Fortune webhook
        
        Args:
            signal_type: "ALERT", "ENTRY", atau "EXIT"
            action: "LONG" atau "SHORT"
            symbol: Trading pair (BTCUSD, ETHUSD, dll)
            price: Harga saat ini
            quantity: Jumlah unit
            target_tp: Take profit price
            target_sl: Stop loss price
            realized_pnl: PnL untuk EXIT signal
            message: Custom message
        """
        
        # Build payload sesuai format webhook
        payload = {
            "type": signal_type.upper(),      # ALERT, ENTRY, EXIT
            "jenis": action.upper(),          # LONG, SHORT
            "symbol": symbol,
            "price": float(price),
            "quantity": float(quantity),
            "target_tp": float(target_tp),
            "target_sl": float(target_sl),
            "message": message,
            "project_id": self.qc_project_id,
            "algorithm_name": self.qc_project_name,
            "source": "quantconnect_live",
            "timestamp": datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ")
        }
        
        # Add PnL for EXIT signals
        if realized_pnl is not None:
            payload["realized_pnl"] = float(realized_pnl)
        
        # Kirim via Notify.Web (built-in QuantConnect)
        try:
            self.notify.web(self.webhook_url, json.dumps(payload))
            self.debug(f"[SENT] {signal_type} {action} {symbol} @ ${price:,.2f}")
            return True
        except Exception as e:
            self.error(f"[ERROR] Failed to send signal: {str(e)}")
            return False
    
    # ================================================================
    # SIGNAL FUNCTIONS
    # ================================================================
    
    def send_alert(self, action: str, symbol: str, price: float,
                   target_tp: float, target_sl: float, reason: str):
        """
        Kirim ALERT - Heads up sebelum entry
        
        Args:
            action: "LONG" atau "SHORT"
            symbol: Trading pair
            price: Expected entry price
            target_tp: Take profit target
            target_sl: Stop loss level
            reason: Alasan/analisis untuk alert
        """
        message = f"{reason}"
        
        self.send_signal(
            signal_type="ALERT",
            action=action,
            symbol=symbol,
            price=price,
            target_tp=target_tp,
            target_sl=target_sl,
            message=message
        )
        
        # Track alert
        self.last_alert[symbol] = {
            "action": action,
            "price": price,
            "tp": target_tp,
            "sl": target_sl,
            "time": self.time
        }
    
    def send_entry(self, action: str, symbol: str, price: float,
                   quantity: float, target_tp: float, target_sl: float):
        """
        Kirim ENTRY signal - Masuk posisi
        
        Args:
            action: "LONG" atau "SHORT"
            symbol: Trading pair
            price: Entry price
            quantity: Position size
            target_tp: Take profit
            target_sl: Stop loss
        """
        direction = "BUY" if action.upper() == "LONG" else "SELL"
        message = f"[{direction}] {action.upper()} Entry @ ${price:,.2f}"
        
        self.send_signal(
            signal_type="ENTRY",
            action=action,
            symbol=symbol,
            price=price,
            quantity=quantity,
            target_tp=target_tp,
            target_sl=target_sl,
            message=message
        )
        
        # Track position
        self.positions[symbol] = {
            "action": action,
            "entry_price": price,
            "quantity": quantity,
            "tp": target_tp,
            "sl": target_sl,
            "time": self.time
        }
    
    def send_exit(self, symbol: str, exit_price: float, reason: str = ""):
        """
        Kirim EXIT signal - Keluar posisi
        
        Args:
            symbol: Trading pair
            exit_price: Exit price
            reason: Alasan exit (TP Hit, SL Hit, Manual, dll)
        """
        if symbol not in self.positions:
            self.debug(f"[WARN] No position found for {symbol}")
            return
        
        pos = self.positions[symbol]
        action = pos["action"]
        entry_price = pos["entry_price"]
        quantity = pos["quantity"]
        
        # Calculate PnL
        if action.upper() == "LONG":
            pnl = (exit_price - entry_price) * quantity
        else:  # SHORT
            pnl = (entry_price - exit_price) * quantity
        
        result = "PROFIT" if pnl >= 0 else "LOSS"
        message = f"[{result}] {reason} | PnL: ${pnl:+,.2f}"
        
        self.send_signal(
            signal_type="EXIT",
            action=action,
            symbol=symbol,
            price=exit_price,
            quantity=quantity,
            realized_pnl=pnl,
            message=message
        )
        
        # Remove position
        del self.positions[symbol]
    
    # ================================================================
    # TRADING LOGIC (CONTOH)
    # ================================================================
    
    def on_data(self, data):
        """
        Main trading logic - Dipanggil setiap ada data baru
        
        CUSTOMIZE bagian ini sesuai strategi trading kamu!
        """
        
        # Skip jika indicator belum ready
        if not self.rsi_btc.is_ready or not self.rsi_eth.is_ready:
            return
        
        # Get current prices
        btc_price = self.securities[self.btc].price
        eth_price = self.securities[self.eth].price
        
        if btc_price <= 0 or eth_price <= 0:
            return
        
        # ============================================================
        # CONTOH STRATEGI: RSI Oversold/Overbought
        # ============================================================
        
        # --- BTC LONG ---
        if self.rsi_btc.current.value < 30:  # RSI Oversold
            if "BTCUSD" not in self.positions:
                # Calculate TP/SL
                tp = round(btc_price * 1.02, 2)  # +2%
                sl = round(btc_price * 0.98, 2)  # -2%
                
                # Kirim ALERT dulu
                if "BTCUSD" not in self.last_alert or \
                   (self.time - self.last_alert["BTCUSD"]["time"]).total_seconds() > 3600:
                    self.send_alert(
                        action="LONG",
                        symbol="BTCUSD",
                        price=btc_price,
                        target_tp=tp,
                        target_sl=sl,
                        reason=f"RSI Oversold ({self.rsi_btc.current.value:.1f}). Preparing LONG entry..."
                    )
                
                # Tunggu konfirmasi lalu kirim ENTRY
                # (Dalam real trading, kamu bisa pakai condition lain)
                # self.send_entry("LONG", "BTCUSD", btc_price, 0.1, tp, sl)
        
        # --- BTC SHORT ---
        elif self.rsi_btc.current.value > 70:  # RSI Overbought
            if "BTCUSD" not in self.positions:
                tp = round(btc_price * 0.98, 2)  # -2%
                sl = round(btc_price * 1.02, 2)  # +2%
                
                if "BTCUSD" not in self.last_alert or \
                   (self.time - self.last_alert["BTCUSD"]["time"]).total_seconds() > 3600:
                    self.send_alert(
                        action="SHORT",
                        symbol="BTCUSD",
                        price=btc_price,
                        target_tp=tp,
                        target_sl=sl,
                        reason=f"RSI Overbought ({self.rsi_btc.current.value:.1f}). Preparing SHORT entry..."
                    )
        
        # --- Check TP/SL for open positions ---
        for symbol, pos in list(self.positions.items()):
            current_price = self.securities[symbol].price
            
            if pos["action"].upper() == "LONG":
                if current_price >= pos["tp"]:
                    self.send_exit(symbol, current_price, "Take Profit Hit!")
                elif current_price <= pos["sl"]:
                    self.send_exit(symbol, current_price, "Stop Loss Hit!")
            else:  # SHORT
                if current_price <= pos["tp"]:
                    self.send_exit(symbol, current_price, "Take Profit Hit!")
                elif current_price >= pos["sl"]:
                    self.send_exit(symbol, current_price, "Stop Loss Hit!")


# ====================================================================
# INSTANT TEST - Untuk test webhook tanpa tunggu market
# ====================================================================

class DragonFortuneTest(QCAlgorithm):
    """
    TEST SIGNAL - Kirim langsung saat deploy!
    Tidak perlu tunggu market buka.
    """
    
    def initialize(self):
        self.set_cash(100000)
        
        # WEBHOOK URL - Dragon Fortune Signal Manager
        self.webhook_url = "https://advisors-litigation-suited-wiki.trycloudflare.com/api/quantconnect/signals/webhook"
        self.qc_project_id = self.project_id
        self.qc_project_name = "Dragon Fortune Test"
        
        self.debug("=" * 60)
        self.debug("[TEST] Sending test signals NOW...")
        self.debug("=" * 60)
        
        # Langsung kirim test signals
        self.send_test_signals()
    
    def send_test_signals(self):
        """Kirim serangkaian test signal"""
        
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
        self.debug("[SENT] ALERT LONG")
        
        # 2. ENTRY (after 2 seconds in real scenario)
        entry_price = btc_price * 1.001
        self.send_webhook({
            "type": "ENTRY",
            "jenis": "LONG",
            "symbol": "BTCUSD",
            "price": entry_price,
            "quantity": 0.1,
            "target_tp": tp,
            "target_sl": sl,
            "message": f"[BUY] LONG Entry @ ${entry_price:,.2f}",
            "project_id": self.qc_project_id,
            "algorithm_name": self.qc_project_name,
            "source": "quantconnect_test"
        })
        self.debug("[SENT] ENTRY LONG")
        
        # 3. EXIT (with profit)
        exit_price = tp * 0.99  # Near TP
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
            "message": f"Take Profit Hit! PnL: ${pnl:+,.2f}",
            "project_id": self.qc_project_id,
            "algorithm_name": self.qc_project_name,
            "source": "quantconnect_test"
        })
        self.debug("[SENT] EXIT LONG")
        
        self.debug("=" * 60)
        self.debug("[SUCCESS] All test signals sent!")
        self.debug("Check your Telegram for notifications!")
        self.debug("=" * 60)
    
    def send_webhook(self, payload):
        """Send webhook using Notify.Web"""
        try:
            self.notify.web(self.webhook_url, json.dumps(payload))
            return True
        except Exception as e:
            self.error(f"[ERROR] {str(e)}")
            return False
    
    def on_data(self, data):
        pass  # Not needed for test


# ====================================================================
# SIMPLE SIGNAL SENDER - Minimal version
# ====================================================================

class SimpleSignal(QCAlgorithm):
    """
    Versi paling simple - copy paste dan ganti values
    """
    
    def initialize(self):
        self.set_cash(100000)
        
        # ============================================================
        # KONFIGURASI SIGNAL
        # ============================================================
        webhook_url = "https://advisors-litigation-suited-wiki.trycloudflare.com/api/quantconnect/signals/webhook"
        
        signal = {
            "type": "ENTRY",           # ALERT, ENTRY, atau EXIT
            "jenis": "LONG",           # LONG atau SHORT
            "symbol": "BTCUSD",        # Trading pair
            "price": 95000.00,         # Harga
            "quantity": 0.1,           # Jumlah
            "target_tp": 96900.00,     # Take Profit
            "target_sl": 93100.00,     # Stop Loss
            "message": "Test signal dari QuantConnect!",
            "project_id": self.project_id,
            "algorithm_name": "Simple Signal Test",
            "source": "quantconnect_live"
        }
        # ============================================================
        
        # Kirim signal
        try:
            self.notify.web(webhook_url, json.dumps(signal))
            self.debug("[SUCCESS] Signal sent! Check Telegram!")
        except Exception as e:
            self.error(f"[ERROR] {str(e)}")
    
    def on_data(self, data):
        pass
