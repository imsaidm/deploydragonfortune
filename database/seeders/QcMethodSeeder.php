<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\QcMethod;

class QcMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'nama_metode' => 'Spot v1 - DragonFortune',
                'market_type' => 'SPOT',
                'pair' => 'BTCUSDT',
                'tf' => '1h',
                'exchange' => 'BINANCE',
                'cagr' => 73.690000,
                'drawdown' => 25.000000,
                'winrate' => 57.000000,
                'lossrate' => 43.000000,
                'prob_sr' => 74.443000,
                'sharpen_ratio' => 1.579000,
                'sortino_ratio' => 2.378000,
                'information_ratio' => -0.515000,
                'turnover' => 22.900000,
                'total_orders' => 181.000000,
                'qc_url' => 'https://www.quantconnect.cloud/backtest/1a090d222c5af75ecf6d3a10c1896685/?theme=chrome',
                'is_active' => false,
            ],
            [
                'nama_metode' => 'Spot v2 - DragonFortune',
                'market_type' => 'SPOT',
                'pair' => 'BTCUSDT',
                'tf' => '1h',
                'exchange' => 'BINANCE',
                'cagr' => 62.508000,
                'drawdown' => 25.000000,
                'winrate' => 71.000000,
                'lossrate' => 29.000000,
                'prob_sr' => 67.000000,
                'sharpen_ratio' => 1.400000,
                'sortino_ratio' => 2.100000,
                'information_ratio' => -0.800000,
                'turnover' => 14.300000,
                'total_orders' => 113.000000,
                'qc_url' => 'https://www.quantconnect.cloud/backtest/815976c9275e1127ffe342b26715ac80/?theme=chrome',
                'api_key' => 'WLkK9CIeo6nDRhQJZqJGFUwdsGuT2SS17g8KVYqx2sLey1OdXg58L8PbCr1dnZEQ',
                'secret_key' => 'VQsyjXTLD3nJUEnM9U7H87rrxLVF7tugmqrAWVJ9ZxgkuePch0SYEbYzh7B5EcQR',
                'is_active' => false,
            ],
            [
                'nama_metode' => 'Spot v3 - DragonFortune',
                'market_type' => 'SPOT',
                'pair' => 'BTCUSDT',
                'tf' => '1h',
                'exchange' => 'BINANCE',
                'cagr' => 71.910000,
                'drawdown' => 20.000000,
                'winrate' => 61.000000,
                'lossrate' => 39.000000,
                'prob_sr' => 86.000000,
                'sharpen_ratio' => 1.800000,
                'sortino_ratio' => 2.600000,
                'information_ratio' => -0.400000,
                'turnover' => 20.800000,
                'total_orders' => 165.000000,
                'qc_url' => 'https://www.quantconnect.cloud/backtest/e8a1411e2d7b66714db2909695ced062/?theme=chrome',
                'api_key' => '1bpO5HlgN29wUIsiJ9EP26iQWoR2Hb9Ftg00VNqZZ4ZWaaxGFq50AKgxAVuSY6hz',
                'secret_key' => 'QQV4q3C0OXraVbphD4dGOVJkA6526SRv2S7ZA3H7iOxc4Vo9rBeOt43oXIaAObcv',
                'is_active' => true,
            ],
            [
                'nama_metode' => 'Futures v1 - DragonFortune',
                'market_type' => 'FUTURES',
                'pair' => 'BTCUSDT',
                'tf' => '1h',
                'exchange' => 'BINANCE',
                'cagr' => 63.467000,
                'drawdown' => 18.100000,
                'winrate' => 69.000000,
                'lossrate' => 31.000000,
                'prob_sr' => 70.408000,
                'sharpen_ratio' => 1.579000,
                'sortino_ratio' => 3.834000,
                'information_ratio' => -0.108000,
                'turnover' => 20.800000,
                'total_orders' => 91.000000,
                'qc_url' => 'https://www.quantconnect.cloud/backtest/532cb3b1dafbdd85afe1845b6a0df506/?theme=chrome',
                'api_key' => 'ErYnZXRH470SFO69rcuYDy3ctDGhUoejl1wxXebH2V1ThjESbr01qsYEgeyTHZLx',
                'secret_key' => 'WmqtrkonvrSiw055u4NGLqzeEkA1TymmFQpdsnv3TZtq5XktxkOrFL79Nlmm5btF',
                'is_active' => true,
            ],
        ];

        foreach ($methods as $method) {
            QcMethod::create($method);
        }

        $this->command->info('✅ Successfully seeded ' . count($methods) . ' trading methods');
    }
}
