<?php
/**
 * Test fungsional login_guard.php terhadap DB lokal.
 *
 * Mensimulasikan percobaan login gagal berulang dan memastikan:
 *   1. di bawah ambang -> boleh lanjut (retry_after = 0)
 *   2. di ambang       -> ditolak (retry_after > 0)
 *   3. login sukses    -> hitungan bersih kembali
 *   4. dimensi email   -> lockout berlaku walau IP berbeda
 *
 * Membersihkan barisnya sendiri setelah selesai.
 * Jalankan: php tools/_test_login_guard.php
 */

require_once __DIR__ . '/../services/db.php';
require_once __DIR__ . '/../services/login_guard.php';

// Guard membaca REMOTE_ADDR; di CLI perlu diset manual.
$_SERVER['REMOTE_ADDR'] = '203.0.113.77';

$email = 'guardtest+' . bin2hex(random_bytes(4)) . '@example.test';
$fails = 0;
$passes = 0;

function check(string $label, bool $cond): void
{
    global $fails, $passes;
    if ($cond) {
        $passes++;
        echo "PASS  {$label}\n";
    } else {
        $fails++;
        echo "FAIL  {$label}\n";
    }
}

try {
    // --- 1. Di bawah ambang email (5): 4 kegagalan masih boleh lanjut ---
    for ($i = 0; $i < 4; $i++) {
        login_guard_record($pdo, 'user', $email, false);
    }
    check('4 kegagalan: masih boleh mencoba', login_guard_check($pdo, 'user', $email) === 0);

    // --- 2. Kegagalan ke-5 memicu lockout (LOGIN_GUARD_MAX_PER_EMAIL = 5) ---
    login_guard_record($pdo, 'user', $email, false);
    $retry = login_guard_check($pdo, 'user', $email);
    check('5 kegagalan: terkunci (retry_after > 0)', $retry > 0);
    check('retry_after <= batas maksimum', $retry <= LOGIN_GUARD_MAX_LOCKOUT);
    echo "      retry_after = {$retry}s\n";

    // --- 3. Lockout email lintas IP: ganti IP, tetap terkunci ---
    $originalIp = $_SERVER['REMOTE_ADDR'];
    $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
    check('IP berbeda: lockout per-email tetap berlaku',
        login_guard_check($pdo, 'user', $email) > 0);
    $_SERVER['REMOTE_ADDR'] = $originalIp;

    // --- 4. Isolasi konteks: panel admin tidak terpengaruh hitungan user ---
    // (email sama, context 'admin' belum punya kegagalan)
    check('context admin terisolasi dari kegagalan user',
        login_guard_check($pdo, 'admin', $email) === 0);

    // --- 5. Setelah sukses, riwayat kegagalan dibersihkan ---
    login_guard_clear($pdo, 'user', $email);
    login_guard_record($pdo, 'user', $email, true);
    check('setelah login sukses: boleh mencoba lagi',
        login_guard_check($pdo, 'user', $email) === 0);

    // --- 6. Baris sukses tidak ikut dihitung sebagai kegagalan ---
    $stats = login_guard_failure_stats($pdo, 'email_hash', login_guard_email_hash($email), 'user');
    check('baris successful=1 tidak dihitung sebagai kegagalan', $stats['count'] === 0);

} finally {
    // Bersihkan data uji.
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email_hash = ?");
    $stmt->execute([login_guard_email_hash($email)]);
    echo "\nCleanup: baris uji dihapus.\n";
}

echo "\nHASIL: {$passes} pass, {$fails} fail\n";
exit($fails === 0 ? 0 : 1);
