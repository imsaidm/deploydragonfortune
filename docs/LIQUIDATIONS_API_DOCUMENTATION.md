# Liquidations API Documentation

## Overview
Dokumentasi untuk implementasi fitur Liquidations menggunakan data dari Coinglass API. Halaman ini menampilkan data likuidasi cryptocurrency dalam bentuk chart dan tabel.

## API Endpoints

### 1. Pair Liquidation History

**Endpoint:** `https://open-api-v4.coinglass.com/api/futures/liquidation/history`
**Method:** `get`
**Description:** `This endpoint provides historical data for long and short liquidations of a trading pair on the exchange.`

**Parameters:**
```
- exchange (required) string defaults to binance. Futures exchange names (e.g., Binance, OKX) .Retrieve supported exchanges via the 'supported-exchange-pair' API.
- symbol (required) string defaults to BTCUSDT. Trading pair symbol (e.g., BTCUSDT, ETHUSDT). Retrieve supported symbols via the 'supported-exchange-pair' API.
- interval (required) string defaults to 1d. Time interval for data aggregation. Supported values: 1m, 3m, 5m, 15m, 30m, 1h, 4h, 6h, 8h, 12h, 1d, 1w.
- limit (optional) integer defaults to 1000. Maximum number of data points to return. Maximum value: 1000.
- start_time (optional) integer. Start timestamp in milliseconds (e.g., 1641522717000).
- end_time (optional) integer. End timestamp in milliseconds (e.g., 1641522717000).
```

**Example Request:**
```curl --request GET \
     --url 'https://open-api-v4.coinglass.com/api/futures/liquidation/history?exchange=Binance&symbol=BTCUSDT&interval=1d&limit=1000&start_time=1675209600000&end_time=1675382400000' \
     --header 'CG-API-KEY: f78a531eb0ef4d06ba9559ec16a6b0c2' \
     --header 'accept: application/json'

**Example Response:**
```json
{
  "code": "0",
  "data": [
    {
      "time": 1675209600000,
      "long_liquidation_usd": "2125001.27718",
      "short_liquidation_usd": "3883950.57033"
    },
    {
      "time": 1675296000000,
      "long_liquidation_usd": "4545051.958",
      "short_liquidation_usd": "3603684.2094"
    }
  ]
}
```

### 2. Coin Liquidation History

**Endpoint:** `https://open-api-v4.coinglass.com/api/futures/liquidation/aggregated-history`
**Method:** `get`
**Description:** `This endpoint provides aggregated historical data for both long and short liquidations of a coin across multiple exchanges.`

**Parameters:**
```
- exchange (required) string defaults to binance. List of exchange names to retrieve data from (e.g., 'Binance, OKX, Bybit')
- symbol (required) string defaults to BTCUSDT. Trading coin (e.g., BTC). Retrieve supported coins via the 'supported-coins' API.
- interval (required) string defaults to 1d. Time interval for data aggregation. Supported values: 1m, 3m, 5m, 15m, 30m, 1h, 4h, 6h, 8h, 12h, 1d, 1w.
- limit (optional) integer defaults to 1000. Maximum number of data points to return. Maximum value: 1000.
- start_time (optional) integer. Start timestamp in milliseconds (e.g., 1641522717000).
- end_time (optional) integer. End timestamp in milliseconds (e.g., 1641522717000).
```

**Example Request:**
```curl --request GET \
     --url 'https://open-api-v4.coinglass.com/api/futures/liquidation/aggregated-history?exchange_list=Binance&symbol=BTC&interval=1d&limit=1000&start_time=1675209600000&end_time=1675382400000' \
     --header 'CG-API-KEY: f78a531eb0ef4d06ba9559ec16a6b0c2' \
     --header 'accept: application/json'

**Example Response:**
```json
{
  "code": "0",
  "data": [
    {
      "time": 1675209600000,
      "aggregated_long_liquidation_usd": 2769932.38868,
      "aggregated_short_liquidation_usd": 5710995.22223
    },
    {
      "time": 1675296000000,
      "aggregated_long_liquidation_usd": 6685039.3096,
      "aggregated_short_liquidation_usd": 5898839.1639
    }
  ]
}
```


### 3. Liquidation Coin List

**Endpoint:** `https://open-api-v4.coinglass.com/api/futures/liquidation/coin-list`
**Method:** `get`
**Description:** `This endpoint provides liquidation data for all coins on a specific exchange.`

**Parameters:**
```
- exchange (required) string defaults to binance. Futures exchange names (e.g., Binance, OKX) .Retrieve supported exchanges via the 'supported-exchange-pair' API.
```

**Example Request:**
```curl --request GET \
     --url 'https://open-api-v4.coinglass.com/api/futures/liquidation/coin-list?exchange=Binance' \
     --header 'CG-API-KEY: f78a531eb0ef4d06ba9559ec16a6b0c2' \
     --header 'accept: application/json'

**Example Response:**
```json
{
  "code": "0",
  "data": [
    {
      "symbol": "EDU",
      "liquidation_usd_24h": 269597.27934,
      "long_liquidation_usd_24h": 92212.0849876,
      "short_liquidation_usd_24h": 177385.1943524,
      "liquidation_usd_12h": 113075.1808608,
      "long_liquidation_usd_12h": 61019.2107374,
      "short_liquidation_usd_12h": 52055.9701234,
      "liquidation_usd_4h": 44160.416653,
      "long_liquidation_usd_4h": 23177.0921527,
      "short_liquidation_usd_4h": 20983.3245003,
      "liquidation_usd_1h": 302.1349,
      "long_liquidation_usd_1h": 302.1349,
      "short_liquidation_usd_1h": 0
    },
    {
      "symbol": "AI16Z",
      "liquidation_usd_24h": 40285.13768358,
      "long_liquidation_usd_24h": 25827.7857128,
      "short_liquidation_usd_24h": 14457.35197078,
      "liquidation_usd_12h": 19953.44924214,
      "long_liquidation_usd_12h": 8680.03749136,
      "short_liquidation_usd_12h": 11273.41175078,
      "liquidation_usd_4h": 11913.65493134,
      "long_liquidation_usd_4h": 5045.69059168,
      "short_liquidation_usd_4h": 6867.96433966,
      "liquidation_usd_1h": 4812.55363334,
      "long_liquidation_usd_1h": 3558.54265168,
      "short_liquidation_usd_1h": 1254.01098166
    },
    ..more
  ]
}
```

### 4. Liquidation Exchange List

**Endpoint:** `https://open-api-v4.coinglass.com/api/futures/liquidation/exchange-list`
**Method:** `get`
**Description:** `This endpoint provides liquidation data for a specific coin across all exchanges.`

**Parameters:**
```
- symbol string defaults to BTC. Trading coin (e.g., ALL, BTC). Retrieve supported coins via the 'supported-coins' API.
- range (required) string Defaults to 1h. Time range for data aggregation. Supported values: 1h, 4h, 12h, 24h.
```

**Example Request:**
```curl --request GET \
     --url 'https://open-api-v4.coinglass.com/api/futures/liquidation/exchange-list?symbol=BTC&range=1h' \
     --header 'CG-API-KEY: f78a531eb0ef4d06ba9559ec16a6b0c2' \
     --header 'accept: application/json'

**Example Response:**
```json
{
  "code": "0",
  "data": [
    {
      "exchange": "All",
      "liquidation_usd": 97418.70418,
      "longLiquidation_usd": 50328.1749,
      "shortLiquidation_usd": 47090.52928
    },
    {
      "exchange": "HTX",
      "liquidation_usd": 46074.8838,
      "longLiquidation_usd": 45382.9974,
      "shortLiquidation_usd": 691.8864
    },
    {
      "exchange": "Binance",
      "liquidation_usd": 42111.6676,
      "longLiquidation_usd": 4370.733,
      "shortLiquidation_usd": 37740.9346
    },
    {
      "exchange": "OKX",
      "liquidation_usd": 7458.87918,
      "longLiquidation_usd": 0,
      "shortLiquidation_usd": 7458.87918
    },
    more..
  ]
}

```

### 5. Liquidation Order

**Endpoint:** `https://open-api-v4.coinglass.com/api/futures/liquidation/order`
**Method:** `get`
**Description:** `This endpoint provides liquidation order data from the past 7 days, including exchange, trading pair, and liquidation amount details.`

**Parameters:**
```
- exchange (required) string defaults to binance. Exchange name (e.g., Binance, OKX). Retrieve supported exchanges via the 'supported-exchange-pair' API. 
- symbol (required) Trading coin (e.g., BTC). Retrieve supported coins via the 'supported-coins' API.
- min_liquidation_amount (required) string defaults to 10000. Minimum threshold for liquidation events. Max 200 records per request.
- start_time (optional) integer. Start timestamp in milliseconds (e.g., 1641522717000).
- end_time (optional) integer. End timestamp in milliseconds (e.g., 1641522717000).
```

**Example Request:**
```curl --request GET \
     --url 'https://open-api-v4.coinglass.com/api/futures/liquidation/aggregated-history?exchange_list=Binance&symbol=BTC&interval=1d&limit=1000&start_time=1675209600000&end_time=1675382400000' \
     --header 'CG-API-KEY: f78a531eb0ef4d06ba9559ec16a6b0c2' \
     --header 'accept: application/json'

**Example Response:**
```json
{
  "code": "0",
  "data": [
    {
      "time": 1675209600000,
      "aggregated_long_liquidation_usd": 2769932.38868,
      "aggregated_short_liquidation_usd": 5710995.22223
    },
    {
      "time": 1675296000000,
      "aggregated_long_liquidation_usd": 6685039.3096,
      "aggregated_short_liquidation_usd": 5898839.1639
    }
  ]
}
```


## WebSocket/Stream Information

### Real-Time Liquidation Orders Push

**Stream URL:** `wss://open-ws.coinglass.com/ws-api?cg-api-key={your_api_key}`
**Protocol:** `[PROTOCOL_PLACEHOLDER]`
**Description:** `The liquidation order snapshot streams provide information on forced liquidation orders for market symbols.`

To subscribe to the liquidationOrders channel, send the following message:
{
    "method": "subscribe",
    "channels": ["liquidationOrders"]
}

Response Example:
{
    "channel": "liquidationOrders",
    "data": [
        {
            "baseAsset": "BTC",
            "exName": "Binance",
            "price": 56738.00,
            "side": 2, //side=1   Long liquidation     side=2   Short liquidation
            "symbol": "BTCUSDT",
            "time": 1725416318379,
            "volUsd": 3858.18400
        }
    ]
}

## Data Structure

### Liquidations Data Model
```javascript
// [PLACEHOLDER - AKAN DIISI BERDASARKAN RESPONSE ACTUAL]
const liquidationsData = {
    // Structure akan diisi setelah mendapat contoh response
};
```

## Implementation Notes

### Frontend Integration
- **File Location:** `dragonfortuneai-tradingdash-laravel/resources/views/derivatives/liquidations.blade.php`
- **Main Controller:** `dragonfortuneai-tradingdash-laravel/public/js/liquidations-hybrid-controller.js`
- **Real-Time Controller:** `dragonfortuneai-tradingdash-laravel/public/js/realtime-liquidations-controller.js`
- **Chart Canvas ID:** `liquidationsMainChart`

### Real-Time Liquidations Table - IMPLEMENTED ✅
- **WebSocket URL:** `wss://open-ws.coinglass.com/ws-api?cg-api-key={api_key}`
- **Channel:** `liquidationOrders`
- **Features Implemented:**
  - ✅ Real-time WebSocket connection to Coinglass
  - ✅ Live liquidation orders display
  - ✅ Filtering by side (Long/Short), exchange, and value
  - ✅ Sorting by time, value, and price
  - ✅ Sound notifications for large liquidations
  - ✅ Pause/Resume functionality
  - ✅ Auto-reconnection with exponential backoff
  - ✅ Statistics display (total, long/short counts, volume)
  - ✅ Responsive design with mobile support
  - ✅ Professional styling matching CryptoQuant/TradingView theme

### Total Liquidations Table - IMPLEMENTED ✅
- **Backend Endpoint (current):** `/api/coinglass/liquidation/aggregated-history`
- **Laravel Controller (current):** `App\Http\Controllers\Coinglass\LiquidationsController@aggregatedHistory`
- **Coinglass API:** `/api/futures/liquidation/aggregated-history`
- **Features Implemented:**
  - ✅ Ranking table with top liquidated coins
  - ✅ Exchange filter (Binance, OKX, Bybit, BitMEX, Bitfinex)
  - ✅ Liquidation breakdown: 1h, 4h, 24h (Long/Short separate)
  - ✅ Price and price change display
  - ✅ Auto-refresh every 30 seconds
  - ✅ CORS issue resolved using Laravel backend proxy
  - ✅ Fallback demo data for testing
  - ✅ Professional ranking badges and color coding
  - ✅ Click coin for detailed modal
  - ✅ Responsive design with mobile support

### Backend Integration
- **Laravel Controller:** `app/Http/Controllers/Coinglass/LiquidationsController.php`
- **Route:** `/derivatives/liquidations`
- **API Method:** `getLiquidationsData()`

### Data Processing Requirements
1. **Real-time Updates:** [AKAN DIISI BERDASARKAN STREAM INFO]
2. **Historical Data:** [AKAN DIISI BERDASARKAN API CAPABILITIES]
3. **Chart Rendering:** [AKAN DIISI BERDASARKAN DATA FORMAT]
4. **Error Handling:** [AKAN DIISI BERDASARKAN API ERROR RESPONSES]

## Chart Configuration

### Chart Types Supported
- [PLACEHOLDER - AKAN DIISI BERDASARKAN DATA TYPE]
- Line Chart untuk trend liquidations
- Bar Chart untuk volume liquidations
- Heatmap untuk exchange breakdown

### Time Intervals
- [PLACEHOLDER - AKAN DIISI BERDASARKAN API CAPABILITIES]
- 1m, 5m, 15m, 1h, 4h, 1d intervals

## Error Handling

### API Error Codes
```javascript
// [PLACEHOLDER - AKAN DIISI BERDASARKAN API DOCUMENTATION]
const errorCodes = {
    // Error codes akan diisi setelah mendapat info dari Coinglass
};
```

---

**Status:** Template Created - Waiting for Coinglass API Details
**Last Updated:** [DATE_PLACEHOLDER]
**Next Steps:** 
1. Dapatkan endpoint URL dan method dari Coinglass
2. Dapatkan contoh response data
3. Identifikasi stream/websocket jika ada
4. Update dokumentasi dengan detail actual
