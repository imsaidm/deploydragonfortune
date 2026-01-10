# TelegramNotifier Library - Developer Guide

## 📚 Overview

Library untuk mengirim notifikasi trading (reminder & signal) dari QuantConnect ke Telegram secara otomatis.

**Features:**
- ✅ Send reminder sebelum signal (approaching crossover)
- ✅ Send trading signal dengan detail lengkap (price, TP, SL, leverage, dll)
- ✅ Support SPOT dan FUTURES trading
- ✅ Auto-generate unique QC_ID untuk tracking
- ✅ Offline mode untuk backtesting

---

## 🚀 Quick Start

### 1. Upload Library ke QuantConnect

Upload file `TelegramNotifier.py` ke project QuantConnect kamu.

### 2. Import di Strategy

```python
from TelegramNotifier import TelegramNotifier
```

### 3. Initialize di `Initialize()` Method

```python
def Initialize(self):
    # ... setup lainnya ...
    
    self.notifier = TelegramNotifier.init(
        algo=self,
        strategy_name="My Strategy Name",
        market_type="SPOT",  # atau "FUTURES"
        webhook_url="https://your-webhook-url.com/api/quantconnect/webhook",
        webhook_token=None  # Optional, untuk security
    )
```

### 4. Enable di `OnData()` Method

```python
def OnData(self, data):
    # Enable notifier on first run
    if not self.notifier.enabled:
        self.notifier.enable()
    
    # ... trading logic ...
```

### 5. Send Notifications

**Reminder:**
```python
self.notifier.send_reminder("BTCUSDT", "Prepare for BUY signal")
```

**Signal:**
```python
self.notifier.send_signal(
    symbol="BTCUSDT",
    side="BUY",
    price=45000,
    tp=46125,
    sl=42750,
    qty=0.01,
    message="BUY signal triggered"
)
```

---

## 📖 API Reference

### `TelegramNotifier.init()`

Initialize notifier instance.

**Parameters:**
- `algo` (QCAlgorithm): Instance algorithm QuantConnect
- `strategy_name` (str): Nama strategy kamu
- `market_type` (str): "SPOT" atau "FUTURES"
- `webhook_url` (str): URL webhook backend
- `webhook_token` (str, optional): Token untuk authentication

**Returns:** TelegramNotifier instance

**Example:**
```python
self.notifier = TelegramNotifier.init(
    algo=self,
    strategy_name="SMA Crossover Strategy",
    market_type="FUTURES",
    webhook_url="https://example.com/api/quantconnect/webhook"
)
```

---

### `notifier.enable()`

Enable notifications. Wajib dipanggil sebelum send notifikasi.

**Example:**
```python
if not self.notifier.enabled:
    self.notifier.enable()
```

---

### `notifier.send_reminder(symbol, message)`

Send reminder notification.

**Parameters:**
- `symbol` (str): Trading symbol (e.g., "BTCUSDT")
- `message` (str): Reminder message

**Example:**
```python
self.notifier.send_reminder(
    "BTCUSDT",
    "⚠️ Prepare for BUY signal - SMA approaching crossover"
)
```

---

### `notifier.send_signal(symbol, side, price, tp, sl, **kwargs)`

Send trading signal notification.

**Required Parameters:**
- `symbol` (str): Trading symbol
- `side` (str): "BUY" atau "SELL"
- `price` (float): Entry price
- `tp` (float): Take profit price
- `sl` (float): Stop loss price

**Optional Parameters (FUTURES):**
- `leverage` (int): Leverage (e.g., 10 untuk 10x)
- `margin_usd` (float): Margin dalam USD
- `qty` (float): Quantity
- `message` (str): Custom message

**Example SPOT:**
```python
self.notifier.send_signal(
    symbol="BTCUSDT",
    side="BUY",
    price=45000,
    tp=46125,
    sl=42750,
    qty=0.01,
    message="BUY signal - Fast SMA crossed above Slow SMA"
)
```

**Example FUTURES:**
```python
self.notifier.send_signal(
    symbol="BTCUSDT",
    side="BUY",
    price=45000,
    tp=46125,
    sl=42750,
    leverage=10,
    margin_usd=100,
    qty=0.01,
    message="LONG signal with 10x leverage"
)
```

---

## 💡 Complete Example - SPOT Trading

```python
from AlgorithmImports import *
from TelegramNotifier import TelegramNotifier

class MySpotStrategy(QCAlgorithm):
    
    def Initialize(self):
        self.SetStartDate(2024, 1, 1)
        
        # Initialize Telegram Notifier
        self.notifier = TelegramNotifier.init(
            algo=self,
            strategy_name="My SPOT Strategy",
            market_type="SPOT",
            webhook_url="https://your-webhook-url.com/api/quantconnect/webhook"
        )
        
        # Setup trading
        self.crypto = self.AddCrypto("BTCUSDT", Resolution.Minute).Symbol
        self.fast = self.SMA(self.crypto, 10)
        self.slow = self.SMA(self.crypto, 30)
        
    def OnData(self, data):
        # Enable notifier
        if not self.notifier.enabled:
            self.notifier.enable()
        
        price = self.Securities[self.crypto].Price
        
        # Send reminder when approaching signal
        if self.IsApproachingCrossover():
            self.notifier.send_reminder(
                "BTCUSDT",
                "⚠️ Prepare for BUY signal"
            )
        
        # Send signal when crossover occurs
        if self.fast.Current.Value > self.slow.Current.Value:
            tp = price * 1.025  # +2.5%
            sl = price * 0.985  # -1.5%
            
            self.notifier.send_signal(
                symbol="BTCUSDT",
                side="BUY",
                price=price,
                tp=tp,
                sl=sl,
                qty=0.01,
                message="BUY signal - SMA crossover"
            )
            
            self.SetHoldings(self.crypto, 0.95)
```

---

## 💡 Complete Example - FUTURES Trading

```python
from AlgorithmImports import *
from TelegramNotifier import TelegramNotifier

class MyFuturesStrategy(QCAlgorithm):
    
    def Initialize(self):
        self.SetStartDate(2024, 1, 1)
        self.leverage = 10
        
        # Initialize Telegram Notifier
        self.notifier = TelegramNotifier.init(
            algo=self,
            strategy_name="My FUTURES Strategy",
            market_type="FUTURES",
            webhook_url="https://your-webhook-url.com/api/quantconnect/webhook"
        )
        
        # Setup trading
        self.crypto = self.AddCrypto("BTCUSDT", Resolution.Minute).Symbol
        self.fast = self.SMA(self.crypto, 10)
        self.slow = self.SMA(self.crypto, 30)
        
    def OnData(self, data):
        # Enable notifier
        if not self.notifier.enabled:
            self.notifier.enable()
        
        price = self.Securities[self.crypto].Price
        
        # Send reminder
        if self.IsApproachingCrossover():
            self.notifier.send_reminder(
                "BTCUSDT",
                f"⚠️ Prepare for LONG position (Leverage: {self.leverage}x)"
            )
        
        # Send signal
        if self.fast.Current.Value > self.slow.Current.Value:
            margin_usd = 100
            tp = price * 1.05   # +5%
            sl = price * 0.98   # -2%
            qty = (margin_usd * self.leverage) / price
            
            self.notifier.send_signal(
                symbol="BTCUSDT",
                side="BUY",
                price=price,
                tp=tp,
                sl=sl,
                leverage=self.leverage,
                margin_usd=margin_usd,
                qty=qty,
                message=f"LONG signal with {self.leverage}x leverage"
            )
            
            self.SetHoldings(self.crypto, 0.95)
```

---

## 🔧 Configuration

### Webhook URL

Ganti `webhook_url` dengan URL backend kamu:

```python
webhook_url="https://your-domain.com/api/quantconnect/webhook"
```

### Security Token (Optional)

Untuk tambah security, set webhook token:

```python
webhook_token="your-secret-token-here"
```

Token ini harus sama dengan yang di backend (`.env`):
```env
QUANTCONNECT_WEBHOOK_TOKEN=your-secret-token-here
```

---

## ❓ FAQ

### Q: Apakah notifikasi terkirim saat backtest?
**A:** Tidak. Saat backtest, notifier dalam mode offline dan hanya log ke console.

### Q: Bagaimana cara enable notifikasi?
**A:** Panggil `self.notifier.enable()` di `OnData()` method.

### Q: Apakah bisa pakai untuk strategy lain selain SMA crossover?
**A:** Ya! Library ini generic dan bisa dipakai untuk strategy apapun.

### Q: Berapa banyak notifikasi yang bisa dikirim?
**A:** Unlimited, selama live trading jalan dan ada signal.

### Q: Apakah perlu setup Telegram bot?
**A:** Ya, tapi itu sudah dihandle oleh backend. Kamu cukup pakai webhook URL yang diberikan.

---

## 🐛 Troubleshooting

### Error: "No module named 'TelegramNotifier'"

**Solusi:** Pastikan file `TelegramNotifier.py` sudah diupload ke QuantConnect project.

### Notifikasi tidak masuk ke Telegram

**Cek:**
1. Apakah `notifier.enable()` sudah dipanggil?
2. Apakah webhook URL benar?
3. Apakah backend masih jalan?
4. Cek log QuantConnect untuk error

### Warning di VSCode

**Solusi:** Tambahkan di awal file:
```python
# pylint: disable=all
# type: ignore
```

Warning ini normal karena VSCode tidak punya library QuantConnect.

---

## 📞 Support

Kalau ada pertanyaan atau issue, hubungi developer backend untuk:
- Webhook URL
- Webhook token (kalau pakai)
- Troubleshooting backend issues

---

## 📄 License

Proprietary - Internal use only
