<?php

use App\Models\QcSignal;

// Trigger for Method 11
QcSignal::create([
    'id_method' => 11,
    'datetime' => now(),
    'type' => 'entry',
    'jenis' => 'buy',
    'price_entry' => 2500,
    'target_tp' => 2600,
    'target_sl' => 2400,
    'message' => 'Test Signal Method 11 - Waiting for Scheduler'
]);

// Trigger for Method 12
QcSignal::create([
    'id_method' => 12,
    'datetime' => now(),
    'type' => 'entry',
    'jenis' => 'sell',
    'price_entry' => 3000,
    'target_tp' => 2900,
    'target_sl' => 3100,
    'message' => 'Test Signal Method 12 - Waiting for Scheduler'
]);

echo "Sinyal untuk Method 11 dan 12 sudah masuk DB!\n";
echo "Silakan tunggu maksimal 10 detik agar scheduler ngirim ke Telegram...\n";
