<?php
/**
 * Login brute-force guard.
 *
 * Dipakai oleh services/auth_login.php (area user) dan
 * services/auth_admin_login.php (panel admin).
 *
 * Prinsip:
 *  - Dua dimensi pembatas: per IP (satu penyerang mencoba banyak akun) dan
 *    per email (banyak IP menyerang satu akun). Batas yang paling ketat menang.
 *  - Exponential backoff: makin banyak kegagalan, makin lama masa tunggu.
 *  - FAIL CLOSED: jika tabel login_attempts tidak ada atau query gagal,
 *    permintaan login ditolak (HTTP 503), bukan diloloskan. Kontrol keamanan
 *    tidak boleh bisa dimatikan hanya dengan membuat query-nya error.
 *  - Hanya menyimpan hash sha256 dari IP dan email, mengikuti pola
 *    email_otp_ip_attempts.ip_hash dan contact_messages.ip_hash.
 *
 * Butuh migrasi database/migrations/011_login_bruteforce_protection.sql.
 */

// Jendela pengamatan kegagalan (detik).
define('LOGIN_GUARD_WINDOW', 900); // 15 menit

// Ambang kegagalan sebelum lockout mulai berlaku.
define('LOGIN_GUARD_MAX_PER_IP_USER', 10);
define('LOGIN_GUARD_MAX_PER_IP_ADMIN', 5);   // panel admin dijaga lebih ketat
define('LOGIN_GUARD_MAX_PER_EMAIL', 5);

// Batas atas lockout (detik) supaya pengguna sah tidak terkunci selamanya.
define('LOGIN_GUARD_MAX_LOCKOUT', 1800); // 30 menit

// Exponential delay pada setiap respons gagal (mikrodetik).
define('LOGIN_GUARD_DELAY_BASE_US', 250000);  // 0,25 s untuk kegagalan pertama
define('LOGIN_GUARD_DELAY_MAX_US', 4000000);  // batas 4 s agar worker tidak habis

/**
 * Exception khusus agar endpoint bisa membedakan "guard tidak bisa dievaluasi"
 * (harus 503) dari error umum lain (500).
 */
class LoginGuardUnavailableException extends RuntimeException {}

/**
 * Hash IP klien. Sengaja hanya memakai REMOTE_ADDR — header X-Forwarded-For
 * bisa dipalsukan klien sehingga akan membuat rate limit gampang dilewati.
 */
function login_guard_ip_hash(): string
{
    return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

/**
 * Hash email agar identitas percobaan login tidak tersimpan sebagai teks biasa.
 * Dinormalkan (trim + lowercase) supaya "A@b.com" dan "a@b.com " dihitung sama.
 */
function login_guard_email_hash(string $email): string
{
    return hash('sha256', strtolower(trim($email)));
}

/**
 * Hitung jumlah kegagalan dan waktu kegagalan terakhir dalam jendela.
 *
 * @return array{count:int,last:int} last = unix timestamp (0 jika tidak ada)
 * @throws LoginGuardUnavailableException bila query tidak dapat dijalankan
 */
function login_guard_failure_stats(PDO $pdo, string $column, string $hash, string $context): array
{
    // Whitelist nama kolom: tidak ada input pengguna yang masuk ke SQL string.
    if ($column !== 'ip_hash' && $column !== 'email_hash') {
        throw new LoginGuardUnavailableException('Invalid guard column.');
    }

    // Window di-interpolasi sebagai integer konstan (bukan placeholder) karena
    // sebagian driver menolak parameter di dalam ekspresi INTERVAL. Nilainya
    // berasal dari konstanta kode, bukan input pengguna.
    $window = (int) LOGIN_GUARD_WINDOW;

    try {
        $sql = "SELECT COUNT(*) AS c, COALESCE(UNIX_TIMESTAMP(MAX(created_at)), 0) AS last
                FROM login_attempts
                WHERE {$column} = ?
                  AND context = ?
                  AND successful = 0
                  AND created_at > (NOW() - INTERVAL {$window} SECOND)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hash, $context]);
        $row = $stmt->fetch();

    } catch (Throwable $e) {
        error_log('Login guard lookup failed: ' . $e->getMessage());
        throw new LoginGuardUnavailableException('Login guard unavailable.', 0, $e);
    }

    return [
        'count' => (int)($row['c'] ?? 0),
        'last'  => (int)($row['last'] ?? 0),
    ];
}

/**
 * Hitung sisa masa lockout untuk satu dimensi.
 * Lockout tumbuh eksponensial: 30s, 60s, 120s, ... dibatasi LOGIN_GUARD_MAX_LOCKOUT.
 */
function login_guard_retry_after(array $stats, int $max): int
{
    if ($stats['count'] < $max || $stats['last'] <= 0) {
        return 0;
    }

    $excess = $stats['count'] - $max;          // 0 pada kegagalan tepat di ambang
    $lockout = 30 * (2 ** min($excess, 10));   // min() mencegah overflow eksponen
    $lockout = (int) min($lockout, LOGIN_GUARD_MAX_LOCKOUT);

    $elapsed = time() - $stats['last'];
    $remaining = $lockout - $elapsed;

    return $remaining > 0 ? $remaining : 0;
}

/**
 * Evaluasi apakah percobaan login harus ditolak.
 *
 * @param string $context 'user' atau 'admin'
 * @return int 0 = boleh lanjut, >0 = ditolak, nilainya detik Retry-After
 * @throws LoginGuardUnavailableException
 */
function login_guard_check(PDO $pdo, string $context, string $email): int
{
    $maxIp = ($context === 'admin')
        ? LOGIN_GUARD_MAX_PER_IP_ADMIN
        : LOGIN_GUARD_MAX_PER_IP_USER;

    $ipStats = login_guard_failure_stats($pdo, 'ip_hash', login_guard_ip_hash(), $context);
    $ipRetry = login_guard_retry_after($ipStats, $maxIp);

    $emailStats = login_guard_failure_stats(
        $pdo,
        'email_hash',
        login_guard_email_hash($email),
        $context
    );
    $emailRetry = login_guard_retry_after($emailStats, LOGIN_GUARD_MAX_PER_EMAIL);

    // Ambil pembatas paling ketat dari kedua dimensi.
    return max($ipRetry, $emailRetry);
}

/**
 * Catat hasil percobaan login.
 *
 * Pencatatan kegagalan bersifat fail-closed: kalau tidak bisa dicatat, guard
 * kehilangan kemampuan menghitung sehingga permintaan harus ditolak 503.
 * Pencatatan sukses tidak fail-closed (login yang sah tidak boleh gagal hanya
 * karena baris audit gagal ditulis) — cukup dicatat ke error log.
 *
 * @throws LoginGuardUnavailableException bila kegagalan tidak dapat dicatat
 */
function login_guard_record(PDO $pdo, string $context, string $email, bool $successful): void
{
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO login_attempts (ip_hash, email_hash, context, successful)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            login_guard_ip_hash(),
            login_guard_email_hash($email),
            $context,
            $successful ? 1 : 0,
        ]);

        // Housekeeping probabilistik (±2% request), pola sama dengan
        // blacklist_token() di jwt_helper.php: menjaga tabel tetap kecil tanpa
        // menambah biaya pada setiap login.
        if (random_int(1, 50) === 1) {
            $pdo->exec("DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)");
        }
    } catch (Throwable $e) {
        error_log('Login guard record failed: ' . $e->getMessage());
        if (!$successful) {
            throw new LoginGuardUnavailableException('Login guard unavailable.', 0, $e);
        }
    }
}

/**
 * Bersihkan riwayat kegagalan setelah login berhasil supaya pengguna sah tidak
 * membawa sisa hitungan dari percobaan sebelumnya.
 * Kegagalan di sini tidak fatal (login sudah tervalidasi).
 */
function login_guard_clear(PDO $pdo, string $context, string $email): void
{
    try {
        $stmt = $pdo->prepare(
            "DELETE FROM login_attempts
             WHERE email_hash = ? AND context = ? AND successful = 0"
        );
        $stmt->execute([login_guard_email_hash($email), $context]);
    } catch (Throwable $e) {
        error_log('Login guard clear failed: ' . $e->getMessage());
    }
}

/**
 * Terapkan penundaan eksponensial pada respons gagal.
 * Memperlambat serangan online tanpa mengganggu pengguna yang salah ketik
 * sekali (kegagalan pertama hanya ~0,25 s).
 */
function login_guard_delay(int $failureCount): void
{
    if ($failureCount < 1) {
        return;
    }
    $delay = LOGIN_GUARD_DELAY_BASE_US * (2 ** min($failureCount - 1, 10));
    usleep((int) min($delay, LOGIN_GUARD_DELAY_MAX_US));
}

/**
 * Jumlah kegagalan terkini (IP + email, ambil yang terbesar) untuk menentukan
 * besar penundaan. Tidak fail-closed: hanya dipakai menghitung delay pada jalur
 * yang memang sudah gagal.
 */
function login_guard_current_failures(PDO $pdo, string $context, string $email): int
{
    try {
        $ip = login_guard_failure_stats($pdo, 'ip_hash', login_guard_ip_hash(), $context);
        $em = login_guard_failure_stats($pdo, 'email_hash', login_guard_email_hash($email), $context);
        return max($ip['count'], $em['count']);
    } catch (Throwable $e) {
        return 1;
    }
}

/**
 * Respons 429 standar untuk login yang diblokir.
 */
function login_guard_reject(int $retryAfter): void
{
    http_response_code(429);
    header('Retry-After: ' . max(1, $retryAfter));
    echo json_encode([
        'ok' => false,
        'message' => 'Terlalu banyak percobaan login. Coba lagi dalam '
            . max(1, (int) ceil($retryAfter / 60)) . ' menit.',
        'retry_after' => max(1, $retryAfter),
    ]);
}

/**
 * Respons 503 ketika guard tidak dapat dievaluasi (fail closed).
 */
function login_guard_unavailable(): void
{
    http_response_code(503);
    header('Retry-After: 60');
    echo json_encode([
        'ok' => false,
        'message' => 'Layanan login sedang tidak tersedia. Coba lagi nanti.',
    ]);
}
