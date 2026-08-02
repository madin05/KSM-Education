<?php
/**
 * Runner migrasi generik (CLI).
 *
 * Pemakaian:
 *   php tools/_run_migration.php database/migrations/012_refresh_token_rotation.sql
 *
 * Semua migrasi di direktori database/migrations ditulis idempoten
 * (IF NOT EXISTS / cek information_schema), sehingga runner ini aman
 * dijalankan ulang dan tidak menghapus data.
 */

require_once __DIR__ . '/../services/db.php';

$rel = $argv[1] ?? '';
if ($rel === '') {
    fwrite(STDERR, "Pemakaian: php tools/_run_migration.php <path/ke/file.sql>\n");
    exit(1);
}

$file = is_file($rel) ? $rel : __DIR__ . '/../' . ltrim($rel, '/\\');
$sql  = @file_get_contents($file);
if ($sql === false) {
    fwrite(STDERR, "Tidak dapat membaca {$file}\n");
    exit(1);
}

// Buang komentar baris agar pemisahan statement tidak terganggu oleh
// tanda ';' yang muncul di dalam komentar.
$clean = [];
foreach (preg_split('/\R/', $sql) as $line) {
    if (preg_match('/^\s*--/', $line)) {
        continue;
    }
    $clean[] = $line;
}

$statements = array_filter(
    array_map('trim', explode(';', implode("\n", $clean))),
    static fn($s) => $s !== ''
);

// Migrasi memakai pola PREPARE/EXECUTE untuk ALTER kondisional. Cabang
// 'SELECT 1' menghasilkan result set; tanpa buffering + closeCursor(), PDO
// menolak statement berikutnya dengan "unbuffered queries are active".
try {
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
} catch (Throwable $e) {
    // Driver non-MySQL: lanjut tanpa penyetelan ini.
}

try {
    foreach ($statements as $stmt) {
        $result = $pdo->query($stmt);
        if ($result instanceof PDOStatement) {
            // Habiskan result set (bila ada) supaya koneksi bersih.
            do {
                $result->fetchAll();
            } while ($result->nextRowset());
            $result->closeCursor();
        }
        echo 'OK: ' . preg_replace('/\s+/', ' ', substr($stmt, 0, 70)) . "\n";
    }

    echo 'MIGRATION APPLIED: ' . basename($file) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'GAGAL: ' . $e->getMessage() . "\n");
    exit(1);
}
