<?php
/**
 * Runner sekali pakai untuk database/migrations/011_login_bruteforce_protection.sql.
 *
 * Hanya menjalankan CREATE TABLE IF NOT EXISTS (idempoten, tidak menghapus data).
 * Jalankan dari CLI: php tools/_run_login_guard_migration.php
 */

require_once __DIR__ . '/../services/db.php';

$file = __DIR__ . '/../database/migrations/011_login_bruteforce_protection.sql';
$sql = file_get_contents($file);
if ($sql === false) {
    fwrite(STDERR, "Tidak dapat membaca {$file}\n");
    exit(1);
}

// Buang komentar baris agar pemisahan statement tidak terganggu.
$lines = preg_split('/\R/', $sql);
$clean = [];
foreach ($lines as $line) {
    if (preg_match('/^\s*--/', $line)) {
        continue;
    }
    $clean[] = $line;
}
$sql = implode("\n", $clean);

$statements = array_filter(array_map('trim', explode(';', $sql)), static fn($s) => $s !== '');

try {
    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
        echo "OK: " . preg_replace('/\s+/', ' ', substr($stmt, 0, 60)) . "...\n";
    }

    // Verifikasi struktur akhir.
    $cols = $pdo->query("SHOW COLUMNS FROM login_attempts")->fetchAll(PDO::FETCH_COLUMN);
    echo "login_attempts columns: " . implode(', ', $cols) . "\n";
    echo "MIGRATION 011 APPLIED\n";
} catch (Throwable $e) {
    fwrite(STDERR, "GAGAL: " . $e->getMessage() . "\n");
    exit(1);
}
