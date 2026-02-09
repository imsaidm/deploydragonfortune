<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\QcSignal;
use App\Models\QcMethod;

// Configuration for Test
$methodId = 3; // Spot v3 - DragonFortune
$symbol = 'ETH/USDT';
$side = 'entry_buy'; // Spot Buy
$price = 2300; // Reference price
$quantity = 0.005; // Small quantity for test
$ratio = 1.0; 

echo "Simulating Signal for Method ID: $methodId...\n";

$method = QcMethod::find($methodId);
if (!$method) {
    die("Error: Strategy with ID $methodId not found.\n");
}

echo "Strategy Name: " . $method->nama_metode . "\n";
echo "Pair: " . $method->pair . "\n";

$isShort = str_contains($side, 'short');
$tpPrice = $isShort ? $price * 0.95 : $price * 1.05;
$slPrice = $isShort ? $price * 1.05 : $price * 0.95;

$signal = QcSignal::create([
    'id_method' => $methodId,
    'datetime' => now(),
    'type' => str_contains($side, 'entry') ? 'entry' : 'exit',
    'jenis' => $side,
    'price_entry' => $price,
    'target_tp' => $tpPrice,
    'target_sl' => $slPrice,
    'message' => "[TEST] Manual SPOT SELL trigger for $symbol ($side)",
    'leverage' => 1,
    'market_type' => 'spot', // NEW: Explicitly set market type
    'quantity' => $quantity,
    'ratio' => $ratio
]);

echo "Signal Created! ID: " . $signal->id . "\n";
echo "Observer should have dispatched the jobs. Check your logs/terminal.\n";
