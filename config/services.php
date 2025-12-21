<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Custom API base URL for frontend fetches (used by deployment envs)
    'api' => [
        'base_url' => env('API_BASE_URL', 'http://localhost:5000'),
    ],

    // Spot microstructure API base URL (CoinGlass integration)
    'spot_microstructure' => [
        // Fallback to the primary API base when a dedicated URL is not provided
        'base_url' => env('SPOT_MICROSTRUCTURE_API_URL', env('API_BASE_URL', 'https://test.dragonfortune.ai')),
    ],

    // CoinGlass (market data)
    'coinglass' => [
        'base_url' => env('COINGLASS_API_URL', 'https://open-api-v4.coinglass.com/api'),
        'key' => env('COINGLASS_API_KEY', ''),
        'timeout' => (int) env('COINGLASS_TIMEOUT', 15),
        'retries' => (int) env('COINGLASS_RETRIES', 2),
        // Cache TTLs (seconds unless stated otherwise)
        'cache_ttl_minutes' => (int) env('COINGLASS_CACHE_TTL', 15), // minutes (macro overlay)
        'cache_ttl' => [
            'etf' => (int) env('COINGLASS_ETF_CACHE_TTL', 30),
            'volatility' => (int) env('COINGLASS_VOLATILITY_CACHE_TTL', 30),
            'funding_rate' => (int) env('COINGLASS_FR_CACHE_TTL', 10),
            'open_interest' => (int) env('COINGLASS_OI_CACHE_TTL', 10),
            'long_short_ratio' => (int) env('COINGLASS_LSR_CACHE_TTL', 10),
            'liquidations' => (int) env('COINGLASS_LIQUIDATIONS_CACHE_TTL', 10),
            'basis' => (int) env('COINGLASS_BASIS_CACHE_TTL', 10),
            'sentiment' => (int) env('COINGLASS_SENTIMENT_CACHE_TTL', 10),
        ],
    ],

    // CryptoQuant (market data)
    'cryptoquant' => [
        'base_url' => env('CRYPTOQUANT_API_URL', 'https://api.cryptoquant.com/v1'),
        'key' => env('CRYPTOQUANT_API_KEY', ''),
        'timeout' => (int) env('CRYPTOQUANT_TIMEOUT', 30),
    ],

    // FRED (macro data)
    'fred' => [
        'base_url' => env('FRED_API_URL', 'https://api.stlouisfed.org/fred/series/observations'),
        'key' => env('FRED_API_KEY', ''),
    ],

    // QuantConnect Cloud Platform API (backtest management, projects, etc)
    'quantconnect' => [
        'base_url' => env('QC_BASE_URL', 'https://www.quantconnect.com/api/v2'),
        'user_id' => env('QC_USER_ID', ''),
        'api_token' => env('QC_API_TOKEN', ''),
        'organization_id' => env('QC_ORGANIZATION_ID', ''),
        'timeout' => (int) env('QC_TIMEOUT', 15),
    ],

    // Binance Spot API
    'binance' => [
        'spot' => [
            // Modes:
            // - auto: use proxy if configured, otherwise direct
            // - direct: call Binance directly
            // - proxy: forward calls to another Dragonfortune instance (useful when Binance is blocked locally)
            // - stub: return simulated data (UI/dev only)
            'mode' => env('BINANCE_SPOT_MODE', 'auto'),
            'base_url' => env('BINANCE_SPOT_BASE_URL', 'https://api.binance.com'),
            // Default account key (used when no ?account=... is provided)
            'default_account' => env('BINANCE_SPOT_DEFAULT_ACCOUNT', 'v1'),
            // Multi-account support (v1/v2). For backward compatibility, v1 falls back to legacy vars.
            'accounts' => [
                'v1' => [
                    'label' => env('BINANCE_SPOT_V1_LABEL', 'Spot v1 - Dragon Fortune'),
                    'api_key' => env('BINANCE_SPOT_V1_API_KEY', env('BINANCE_SPOT_API_KEY', '')),
                    'api_secret' => env('BINANCE_SPOT_V1_API_SECRET', env('BINANCE_SPOT_API_SECRET', '')),
                ],
                'v2' => [
                    'label' => env('BINANCE_SPOT_V2_LABEL', 'Spot v2 - Dragon Fortune'),
                    'api_key' => env('BINANCE_SPOT_V2_API_KEY', ''),
                    'api_secret' => env('BINANCE_SPOT_V2_API_SECRET', ''),
                ],
            ],
            // Legacy single-account vars (kept for compatibility)
            'api_key' => env('BINANCE_SPOT_API_KEY', ''),
            'api_secret' => env('BINANCE_SPOT_API_SECRET', ''),
            'timeout' => (int) env('BINANCE_SPOT_TIMEOUT', 10),
            'recv_window' => (int) env('BINANCE_SPOT_RECV_WINDOW', 5000),
            'verify_ssl' => env('BINANCE_SPOT_VERIFY_SSL', true),
            'proxy_base_url' => env('BINANCE_SPOT_PROXY_BASE_URL', ''),
            'proxy_token' => env('BINANCE_SPOT_PROXY_TOKEN', ''),
            'proxy_verify_ssl' => env('BINANCE_SPOT_PROXY_VERIFY_SSL', true),
            'stub_data' => env('BINANCE_SPOT_STUB_DATA', false),
        ],
    ],

];
