<?php
/**
 * tools/create_admin.php
 *
 * Membuat atau memperbarui akun administrator. HANYA untuk dijalankan
 * dari CLI (php tools/create_admin.php ...), tidak boleh via HTTP.
 *
 * Cara pakai (URUTAN PRIORITAS SUMBER PASSWORD):
 *
 *   1) stdin base64 - PALING AMAN, dipakai untuk remote/SSH:
 *      printf '%s' 'P@ssw0rd' | base64 | \
 *        php tools/create_admin.php admin@gmail.com --password-base64-stdin "Administrator"
 *
 *   2) stdin polos (tanpa trailing newline yang ikut terbaca):
 *      printf '%s' 'P@ssw0rd' | php tools/create_admin.php admin@gmail.com --password-stdin
 *
 *   3) environment variable:
 *      ADMIN_PASSWORD='P@ssw0rd' php tools/create_admin.php admin@gmail.com
 *
 *   4) argumen langsung (HANYA untuk shell lokal; JANGAN lewat SSH/PowerShell
 *      karena karakter seperti @ ! $ " ' dapat diubah oleh shell sehingga
 *      password yang tersimpan berbeda dari yang Anda maksud):
 *      php tools/create_admin.php admin@gmail.com '123123' "Administrator"
 *
 * Jika akun sudah ada, script akan menaikkan role menjadi 'admin',
 * mengaktifkan kembali akun, dan mengganti password.
 *
 * Setelah menyimpan, script membaca ulang hash dari database dan menjalankan
 * password_verify(). Jika verifikasi gagal, script keluar dengan kode error
 * sehingga password yang rusak tidak pernah lolos tanpa diketahui.
 */


if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Script ini hanya dapat dijalankan dari command line.\n";
    exit(1);
}

require_once __DIR__ . '/../services/db.php';

$email = $argv[1] ?? 'admin@gmail.com';
$arg2  = $argv[2] ?? null;
$name  = $argv[3] ?? 'Administrator';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Email tidak valid: {$email}\n");
    exit(1);
}

/**
 * Menentukan password dari sumber yang tidak dapat dirusak shell.
 * Mengembalikan array [password, sumber].
 */
function resolve_admin_password(?string $arg2, string $email): array
{

    if ($arg2 === '--password-base64-stdin' || $arg2 === '--password-stdin') {
        $raw = stream_get_contents(STDIN);
        if ($raw === false) {
            fwrite(STDERR, "Gagal membaca password dari stdin.\n");
            exit(1);
        }
        // Buang newline terakhir yang ditambahkan pipe/echo, bukan spasi di dalam password.
        $raw = preg_replace('/\r?\n\z/', '', $raw);

        if ($arg2 === '--password-base64-stdin') {
            $decoded = base64_decode(trim((string)$raw), true);
            if ($decoded === false) {
                fwrite(STDERR, "Input stdin bukan base64 yang valid.\n");
                exit(1);
            }
            return [$decoded, 'stdin-base64'];
        }
        return [(string)$raw, 'stdin'];
    }

    $fromEnv = getenv('ADMIN_PASSWORD');
    if (is_string($fromEnv) && $fromEnv !== '') {
        return [$fromEnv, 'env:ADMIN_PASSWORD'];
    }

    if ($arg2 !== null && $arg2 !== '') {
        return [$arg2, 'argv'];
    }

    fwrite(STDERR,
        "Password wajib diisi. Pilih salah satu:\n" .
        "  printf '%s' 'PASSWORD' | base64 | php tools/create_admin.php {$email} --password-base64-stdin\n" .
        "  ADMIN_PASSWORD='PASSWORD' php tools/create_admin.php {$email}\n" .
        "  php tools/create_admin.php {$email} 'PASSWORD'   (hanya shell lokal)\n"
    );
    exit(1);
}

[$password, $passwordSource] = resolve_admin_password($arg2, $email);


if (strlen($password) < 6) {
    fwrite(STDERR, "Password minimal 6 karakter (terbaca " . strlen($password) . " karakter dari {$passwordSource}).\n");
    exit(1);
}

// Cetak sidik jari, bukan passwordnya, agar bisa dipastikan tidak ada karakter
// yang hilang/berubah saat melewati shell atau SSH.
echo "Sumber password : {$passwordSource}\n";
echo "Panjang password: " . strlen($password) . " karakter\n";
echo "SHA256 (12 char): " . substr(hash('sha256', $password), 0, 12) . "\n";
if ($passwordSource === 'argv') {
    fwrite(STDERR,
        "PERINGATAN: password diambil dari argumen CLI. Bila perintah ini dijalankan\n" .
        "lewat SSH/PowerShell, karakter khusus (@ ! $ \" ') dapat berubah. Bandingkan\n" .
        "SHA256 di atas dengan nilai di mesin Anda, atau gunakan --password-base64-stdin.\n"
    );
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

    // Verifikasi balik: baca hash yang benar-benar tersimpan lalu uji dengan
    // password yang dipakai. Ini mencegah kasus "akun terlihat dibuat tetapi
    // login selalu gagal" seperti yang terjadi di produksi.
    $check = $pdo->prepare(
        "SELECT id, role, account_status, password_hash FROM users WHERE email = ? LIMIT 1"
    );
    $check->execute([$email]);
    $saved = $check->fetch();

    if (!$saved) {
        fwrite(STDERR, "VERIFIKASI GAGAL: baris admin tidak ditemukan setelah disimpan.\n");
        exit(2);
    }
    if (!password_verify($password, (string)$saved['password_hash'])) {
        fwrite(STDERR, "VERIFIKASI GAGAL: password_verify() menolak hash yang baru disimpan.\n");
        exit(2);
    }
    if (($saved['role'] ?? '') !== 'admin' || ($saved['account_status'] ?? '') !== 'active') {
        fwrite(STDERR, "VERIFIKASI GAGAL: role/status bukan admin/active (role={$saved['role']}, status={$saved['account_status']}).\n");
        exit(2);
    }

    echo "VERIFIKASI OK: password_verify() cocok, role=admin, status=active.\n";
    echo "Login di: /admin/login_admin.php\n";
    echo "PERINGATAN: ganti password ini sebelum dipakai di produksi.\n";
    exit(0);

} catch (Throwable $e) {
    fwrite(STDERR, 'Gagal membuat admin: ' . $e->getMessage() . "\n");
    exit(1);
}
