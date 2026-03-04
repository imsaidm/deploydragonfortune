<?php
/**
 * ============================================================
 * SIMULATE SIGNAL — Insert test signal into qc_signal table
 * ============================================================
 * 
 * Usage: php scripts/simulate_signal.php
 * 
 * This script bootstraps Laravel and inserts a signal via Eloquent
 * so the QcSignalObserver fires automatically → dispatches ProcessSignalJob.
 * 
 * You can also pass CLI arguments to override defaults:
 *   php scripts/simulate_signal.php --ratio=0.886 --qty=0.036 --price=2650.50
 */

// ── Bootstrap Laravel ─────────────────────────────────────────
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\QcSignal;
use Illuminate\Support\Facades\Log;

// ── Parse CLI arguments ───────────────────────────────────────
$defaults = [
    'id_method'   => 66,
    'type'        => 'entry',
    'jenis'       => 'long',
    'leverage'    => 10,
    'price_entry' => 2650.00,    // ETH approximate price — adjust if needed
    'price_exit'  => 0,
    'target_tp'   => 2750.00,
    'target_sl'   => 2580.00,
    'quantity'    => 0.036,
    'ratio'       => 0.886,
    'market_type' => 'future',
    'message'     => '',
];

// Override with CLI args (e.g. --ratio=0.5 --qty=0.02)
$args = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--')) {
        $parts = explode('=', ltrim($arg, '-'), 2);
        if (count($parts) === 2) {
            $args[$parts[0]] = $parts[1];
        }
    }
}

$idMethod   = (int)    ($args['method']   ?? $defaults['id_method']);
$type       = (string) ($args['type']     ?? $defaults['type']);
$jenis      = (string) ($args['jenis']    ?? $defaults['jenis']);
$leverage   = (int)    ($args['leverage'] ?? $defaults['leverage']);
$priceEntry = (float)  ($args['price']    ?? $defaults['price_entry']);
$priceExit  = (float)  ($args['price_exit'] ?? $defaults['price_exit']);
$targetTp   = (float)  ($args['tp']       ?? $defaults['target_tp']);
$targetSl   = (float)  ($args['sl']       ?? $defaults['target_sl']);
$quantity   = (float)  ($args['qty']      ?? $defaults['quantity']);
$ratio      = (float)  ($args['ratio']    ?? $defaults['ratio']);
$marketType = (string) ($args['market']   ?? $defaults['market_type']);

// Build message
$symbolMap = [
    66 => 'ETHUSDT',
];
$symbol = $symbolMap[$idMethod] ?? 'ETHUSDT';

$message = "Jenis\nSignal\nSignal\n{$type}\nSymbol\n{$symbol}\nTime: " . now()->format('Y-m-d H:i');

// ── Display summary before insert ────────────────────────────
echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║           🧪 SIMULATE SIGNAL — DRY RUN PREVIEW         ║\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
echo "║  Method ID  : {$idMethod}\n";
echo "║  Symbol     : {$symbol}\n";
echo "║  Type       : {$type}\n";
echo "║  Jenis      : {$jenis}\n";
echo "║  Leverage   : {$leverage}x\n";
echo "║  Entry Price: \$" . number_format($priceEntry, 2) . "\n";
echo "║  Target TP  : \$" . number_format($targetTp, 2) . "\n";
echo "║  Target SL  : \$" . number_format($targetSl, 2) . "\n";
echo "║  Quantity   : {$quantity}\n";
echo "║  Ratio      : {$ratio}\n";
echo "║  Market Type: {$marketType}\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
echo "║                                                        ║\n";

// Calculate expected order sizing for preview
echo "║  📊 EXPECTED ORDER SIZING (Method 2 — Ratio):          ║\n";
echo "║  Formula: investorBalance × ratio / currentPrice       ║\n";
echo "║  (Actual balance will be fetched during execution)      ║\n";
echo "║                                                        ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// ── Confirmation ─────────────────────────────────────────────
echo "⚠️  This will INSERT a real signal into qc_signal and trigger ProcessSignalJob.\n";
echo "⚠️  The queue worker must be running to process the signal.\n\n";
echo "Continue? (y/n): ";

$confirm = trim(fgets(STDIN));
if (strtolower($confirm) !== 'y') {
    echo "❌ Cancelled.\n";
    exit(0);
}

// ── Insert via Eloquent (triggers Observer → ProcessSignalJob) ──
try {
    $signal = QcSignal::create([
        'id_method'   => $idMethod,
        'datetime'    => now(),
        'type'        => $type,
        'jenis'       => $jenis,
        'leverage'    => $leverage,
        'price_entry' => $priceEntry,
        'price_exit'  => $priceExit,
        'target_tp'   => $targetTp,
        'target_sl'   => $targetSl,
        'real_tp'     => 0,
        'real_sl'     => 0,
        'quantity'    => $quantity,
        'ratio'       => $ratio,
        'market_type' => $marketType,
        'message'     => $message,
        'telegram_sent' => 0,
        'telegram_processing' => 0,
    ]);

    echo "\n✅ Signal CREATED successfully!\n";
    echo "   Signal ID   : {$signal->id}\n";
    echo "   Method ID   : {$signal->id_method}\n";
    echo "   Ratio       : {$signal->ratio}\n";
    echo "   Quantity    : {$signal->quantity}\n";
    echo "   Market Type : {$signal->market_type}\n";
    echo "\n";
    echo "📌 Observer has dispatched ProcessSignalJob.\n";
    echo "📌 Make sure queue worker is running: php artisan queue:work\n";
    echo "\n";
    echo "🔍 Monitor logs with: tail -f storage/logs/laravel.log\n";
    echo "   Or on Windows:     Get-Content storage/logs/laravel.log -Wait -Tail 50\n";
    echo "\n";

    Log::info("🧪 SIMULATE: Signal ID {$signal->id} created for Method {$idMethod} | Ratio: {$ratio} | Qty: {$quantity} | Price: {$priceEntry}");

} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
