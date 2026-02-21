<?php
// ==========================================
// KONFIGURASI DATABASE
// ==========================================
$host = '127.0.0.1';
$db   = 'xgboostqc';       // Nama Database
$user = 'root';            // Ganti dengan username DB kamu
$pass = 'password_kamu';   // Ganti dengan password DB kamu

try {
    // Membuat koneksi PDO
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query Insert dengan format yang sudah disesuaikan dengan tabel kamu
    $sql = "INSERT INTO `qc_signal` (
        `id_method`, `datetime`, `type`, `jenis`, `leverage`, 
        `price_entry`, `price_exit`, `target_tp`, `target_sl`, 
        `real_tp`, `real_sl`, `message`, `telegram_sent`, 
        `telegram_processing`, `quantity`, `ratio`, `market_type`
    ) VALUES (
        10, 
        NOW(), 
        'entry', 
        'buy', 
        20, 
        96500.50, 
        0.00, 
        98000.00, 
        95000.00, 
        0.00, 
        0.00, 
        '🚀 TEST DUMMY SIGNAL PDO\n\nTesting sistem antrean Anti-Dobel dan Anti-Spam Telegram.\nPastikan pesan ini masuk ke 3 grup dengan jeda 0.5 detik.\n\nPair: BTCUSDT', 
        0, 
        0, 
        0.05, 
        1.5, 
        'future'
    )";
    
    // Eksekusi Query
    $pdo->exec($sql);

    echo "<div style='font-family: sans-serif; padding: 20px; background: #e6ffed; border: 1px solid #b7ebc6; border-radius: 8px;'>";
    echo "<h2 style='color: #1a7f37; margin-top: 0;'>✅ Insert Berhasil!</h2>";
    echo "Sinyal dummy untuk <b>id_method = 10</b> sudah masuk ke database dengan status <i>telegram_sent = 0</i>.<br><br>";
    echo "Tunggu beberapa detik dan silakan pantau ke-3 grup Telegram kamu.";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div style='font-family: sans-serif; padding: 20px; background: #ffebe9; border: 1px solid #ff8182; border-radius: 8px;'>";
    echo "<h2 style='color: #cf222e; margin-top: 0;'>❌ Gagal Insert</h2>";
    echo "Error: " . $e->getMessage();
    echo "</div>";
}