<?php
/**
 * tools/create_admin.php
 *
 * Membuat atau memperbarui akun administrator. HANYA untuk dijalankan
 * dari CLI (php tools/create_admin.php ...), tidak boleh via HTTP.
 *
 * Contoh:
 *   php tools/create_admin.php admin@gmail.com 123123 "Administrator"
 *
 * Jika akun sudah ada, script akan menaikkan role menjadi 'admin',
 * mengaktifkan kembali akun, dan mengganti password.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Script ini hanya dapat dijalankan dari command line.\n";
    exit(1);
}

require_once __DIR__ . '/../services/db.php';

$email    = $argv[1] ?? 'admin@gmail.com';
$password = $argv[2] ?? null;
$name     = $argv[3] ?? 'Administrator';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Email tidak valid: {$email}\n");
    exit(1);
}

if ($password === null || $password === '') {
    fwrite(STDERR, "Password wajib diisi.\nContoh: php tools/create_admin.php admin@gmail.com 123123\n");
    exit(1);
}

if (strlen($password) < 6) {
    fwrite(STDERR, "Password minimal 6 karakter.\n");
    exit(1);
}

try {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $update = $pdo->prepare(
            "UPDATE users
             SET password_hash = ?, role = 'admin', account_status = 'active',
                 name = COALESCE(NULLIF(name, ''), ?), password_changed_at = NOW()
             WHERE id = ?"
        );
        $update->execute([$hash, $name, (int)$existing['id']]);
        echo "Akun admin diperbarui (id={$existing['id']}, email={$email}).\n";
    } else {
        $insert = $pdo->prepare(
            "INSERT INTO users (name, email, password_hash, role, account_status, created_at, password_changed_at)
             VALUES (?, ?, ?, 'admin', 'active', NOW(), NOW())"
        );
        $insert->execute([$name, $email, $hash]);
        echo "Akun admin dibuat (id={$pdo->lastInsertId()}, email={$email}).\n";
    }

    echo "Login di: /admin/login_admin.php\n";
    echo "PERINGATAN: ganti password ini sebelum dipakai di produksi.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Gagal membuat admin: ' . $e->getMessage() . "\n");
    exit(1);
}
