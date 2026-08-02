<?php
/**
 * POST /services/verify_email_otp.php — verifikasi OTP email pendaftaran.
 *
 * Body JSON: { "email": "...", "otp": "123456" }
 *
 * Sukses: email ditandai verified, OTP dihapus (dikonsumsi), sesi + JWT user
 * diterbitkan sehingga klien bisa langsung masuk dashboard.
 *
 * Keamanan:
 * - Seluruh alur baca-verifikasi-konsumsi berjalan di dalam SATU transaksi
 *   dengan SELECT ... FOR UPDATE sehingga dua request paralel dengan OTP yang
 *   sama tidak bisa dua kali mengonsumsi OTP (race condition).
 * - Semua kegagalan memakai satu pesan generik: klien tidak bisa membedakan
 *   "email tidak terdaftar", "sudah terverifikasi", "OTP kadaluarsa", maupun
 *   "OTP salah" (mencegah user enumeration).
 * - Rate limit per IP: maksimal KSMEDU_OTP_IP_MAX_VERIFY percobaan dalam
 *   KSMEDU_OTP_IP_WINDOW_MINUTES menit.
 */

// Verifikasi selalu milik area pengguna: jangan pernah menyentuh sesi admin.
define('KSMEDU_FORCE_CONTEXT', 'user');
require_once __DIR__ . '/auth_context.php';
ksmedu_session_start(KSMEDU_CTX_USER);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_helper.php';
require_once __DIR__ . '/email_otp_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

// Satu-satunya pesan kegagalan verifikasi yang boleh sampai ke klien.
$genericFailure = 'Kode OTP salah atau sudah tidak berlaku. Silakan kirim ulang OTP.';

try {
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Data input tidak valid!']);
        exit;
    }

    $email = trim((string)($data['email'] ?? ''));
    $otp = trim((string)($data['otp'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Format email tidak valid!']);
        exit;
    }

    if (!preg_match('/^\d{6}$/', $otp)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Kode OTP harus 6 digit angka.']);
        exit;
    }

    // Rate limit IP diperiksa sebelum menyentuh data user sama sekali.
    if (otp_ip_rate_limited($pdo, 'verify', KSMEDU_OTP_IP_MAX_VERIFY)) {
        http_response_code(429);
        echo json_encode([
            'ok' => false,
            'message' => 'Terlalu banyak percobaan verifikasi. Coba lagi dalam '
                . (int)KSMEDU_OTP_IP_WINDOW_MINUTES . ' menit.'
        ]);
        exit;
    }

    // Dicatat di luar transaksi verifikasi supaya rollback tidak menghapus
    // jejak rate limit.
    otp_record_ip_attempt($pdo, 'verify');

    // ===== TRANSAKSI: baca OTP -> hitung attempt -> konsumsi -> verifikasi =====
    $pdo->beginTransaction();

    // Baris user dikunci agar email_verified_at tidak berubah di tengah alur.
    $stmt = $pdo->prepare(
        "SELECT id, name, email, role, email_verified_at
         FROM users
         WHERE email = ? AND (account_status = 'active' OR account_status IS NULL)
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Email tidak ada / sudah terverifikasi: balasan sama dengan OTP salah.
    if (!$user || !empty($user['email_verified_at'])) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => $genericFailure]);
        exit;
    }

    $userId = (int)$user['id'];

    // SELECT ... FOR UPDATE: request paralel akan menunggu di sini dan setelah
    // lock dilepas hanya melihat OTP yang sudah dikonsumsi (null).
    $pending = otp_lock_latest_pending($pdo, $userId);

    if (!$pending || (int)$pending['is_expired'] === 1) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => $genericFailure]);
        exit;
    }

    // attempt_count dibaca DI DALAM transaksi (nilai terbaru yang terkunci).
    if ((int)$pending['attempt_count'] >= KSMEDU_OTP_MAX_ATTEMPTS) {
        $pdo->rollBack();
        http_response_code(429);
        echo json_encode(['ok' => false, 'message' => $genericFailure]);
        exit;
    }

    $verificationId = (int)$pending['id'];

    if (!password_verify($otp, (string)$pending['otp_hash'])) {
        // Increment atomik pada baris yang sudah terkunci dan masih aktif.
        $bump = $pdo->prepare(
            'UPDATE email_verifications
             SET attempt_count = attempt_count + 1
             WHERE id = ? AND consumed_at IS NULL'
        );
        $bump->execute([$verificationId]);

        if ($bump->rowCount() !== 1) {
            // OTP dikonsumsi/dibatalkan request lain: jangan simpan apa pun.
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => $genericFailure]);
            exit;
        }

        // Commit agar hitungan percobaan tetap tercatat walau verifikasi gagal.
        $pdo->commit();
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => $genericFailure]);
        exit;
    }

    // Konsumsi OTP secara atomik: hanya satu request yang bisa mendapat
    // rowCount() === 1 untuk baris yang sama.
    $consume = $pdo->prepare(
        'UPDATE email_verifications
         SET consumed_at = CURRENT_TIMESTAMP
         WHERE id = ? AND consumed_at IS NULL'
    );
    $consume->execute([$verificationId]);

    if ($consume->rowCount() !== 1) {
        // Request lain sudah mengonsumsi OTP ini.
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => $genericFailure]);
        exit;
    }

    $verify = $pdo->prepare(
        'UPDATE users
         SET email_verified_at = CURRENT_TIMESTAMP
         WHERE id = ? AND email_verified_at IS NULL'
    );
    $verify->execute([$userId]);

    if ($verify->rowCount() !== 1) {
        // Akun sudah diverifikasi request lain: batalkan seluruh perubahan.
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => $genericFailure]);
        exit;
    }

    // OTP tidak boleh bisa dipakai ulang: baris verifikasi dihapus.
    $cleanup = $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?');
    $cleanup->execute([$userId]);

    if ($cleanup->rowCount() < 1) {
        // Baris yang baru dikonsumsi wajib ikut terhapus; bila tidak, keadaan
        // database tidak sesuai harapan dan tidak boleh di-commit.
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => $genericFailure]);
        exit;
    }

    // Commit hanya setelah OTP dikonsumsi DAN email_verified_at terisi.
    $pdo->commit();

    // Login user (session + JWT) mengikuti pola auth_login.php.
    $_SESSION = [];
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];

    $userData = [
        'id' => $userId,
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role']
    ];
    $accessToken = generate_access_token($userData);
    $refreshToken = generate_refresh_token($userData);

    echo json_encode([
        'ok' => true,
        'message' => 'Verifikasi berhasil!',
        'user' => [
            'id' => $userId,
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ],
        'access_token' => $accessToken['token'],
        'refresh_token' => $refreshToken['token'],
        'expires_in' => $accessToken['expires_in']
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Verify email OTP error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Terjadi kesalahan sistem. Coba lagi nanti.']);
}
