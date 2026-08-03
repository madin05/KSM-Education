<?php
/**
 * POST /services/resend_email_otp.php — kirim ulang OTP verifikasi email.
 *
 * Body JSON: { "email": "..." }
 *
 * Batasan: cooldown 60 detik antar permintaan dan maksimal 3 kali dalam 15
 * menit per akun. OTP baru selalu membatalkan OTP sebelumnya.
 *
 * Keamanan:
 * - Respons selalu generik. Email tidak terdaftar, email sudah terverifikasi,
 *   cooldown per akun, batas per akun, maupun kegagalan kirim email semuanya
 *   menghasilkan balasan yang identik sehingga tidak dapat dipakai untuk
 *   menebak keberadaan sebuah akun (user enumeration).
 * - Satu-satunya penolakan eksplisit adalah rate limit per IP
 *   (KSMEDU_OTP_IP_MAX_RESEND per KSMEDU_OTP_IP_WINDOW_MINUTES menit) yang
 *   tidak bergantung pada email mana pun.
 */

define('KSMEDU_FORCE_CONTEXT', 'user');
require_once __DIR__ . '/auth_context.php';
ksmedu_session_start(KSMEDU_CTX_USER);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_otp_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

/**
 * Balasan tunggal untuk semua jalur "permintaan diterima", terlepas dari
 * apakah email benar-benar ada atau email benar-benar dikirim.
 */
function otp_resend_generic_response(): void
{
    echo json_encode([
        'ok' => true,
        'message' => 'Jika email terdaftar dan belum terverifikasi, kode OTP baru telah dikirim.',
        'cooldown' => KSMEDU_OTP_RESEND_COOLDOWN
    ]);
}

try {
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Data input tidak valid!']);
        exit;
    }

    $email = trim((string)($data['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Format email tidak valid!']);
        exit;
    }

    // Rate limit per IP diperiksa lebih dulu, tanpa menyentuh tabel users.
    if (otp_ip_rate_limited($pdo, 'resend', KSMEDU_OTP_IP_MAX_RESEND)) {
        http_response_code(429);
        echo json_encode([
            'ok' => false,
            'message' => 'Terlalu banyak permintaan kirim ulang. Coba lagi dalam '
                . (int)KSMEDU_OTP_IP_WINDOW_MINUTES . ' menit.'
        ]);
        exit;
    }

    otp_record_ip_attempt($pdo, 'resend');

    // Akun 'deleted' ikut diambil: pendaftaran ulang atas email tersebut
    // menerbitkan OTP reaktivasi, dan tombol "kirim ulang" harus tetap bekerja
    // untuk OTP itu. Status lain (mis. 'suspended') ditolak di bawah.
    $stmt = $pdo->prepare(
        "SELECT id, name, email, email_verified_at, account_status
         FROM users
         WHERE email = ?
         LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $accountStatus = $user ? (string)($user['account_status'] ?? 'active') : '';

    // Email tidak terdaftar atau status akun tidak berhak menerima OTP:
    // balasan generik yang sama.
    if (!$user || !in_array($accountStatus, ['active', 'deleted'], true)) {
        otp_resend_generic_response();
        exit;
    }

    $userId = (int)$user['id'];

    // OTP terakhir menentukan apakah ada proses verifikasi yang sedang berjalan
    // dan payload registrasi tertunda yang harus dibawa ke OTP baru.
    $pending = otp_latest_pending($pdo, $userId);
    $isReactivation = $pending ? !empty($pending['is_reactivation']) : false;

    // Akun aktif yang sudah terverifikasi tidak punya OTP untuk dikirim ulang.
    if ($accountStatus === 'active' && !empty($user['email_verified_at'])) {
        otp_resend_generic_response();
        exit;
    }

    // Akun yang di-soft-delete hanya boleh menerima kiriman ulang bila memang
    // ada OTP reaktivasi yang masih menunggu.
    if ($accountStatus === 'deleted' && !$isReactivation) {
        otp_resend_generic_response();
        exit;
    }


    // Cooldown 60 detik dihitung dari OTP terakhir yang diterbitkan
    // (termasuk yang sudah dibatalkan), bukan hanya yang masih aktif.
    $lastStmt = $pdo->prepare(
        'SELECT TIMESTAMPDIFF(SECOND, created_at, CURRENT_TIMESTAMP) AS age_seconds
         FROM email_verifications WHERE user_id = ? ORDER BY id DESC LIMIT 1'
    );
    $lastStmt->execute([$userId]);
    $lastAge = $lastStmt->fetchColumn();

    // Masih dalam cooldown: OTP tidak diterbitkan, namun balasan tetap generik.
    if ($lastAge !== false && (int)$lastAge < KSMEDU_OTP_RESEND_COOLDOWN) {
        otp_resend_generic_response();
        exit;
    }

    // Maksimal 3 kirim ulang dalam 15 menit terakhir (OTP pertama dari
    // registrasi memiliki resend_count = 0 sehingga tidak ikut dihitung).
    $windowStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM email_verifications
         WHERE user_id = ? AND resend_count > 0
           AND created_at >= (CURRENT_TIMESTAMP - INTERVAL 15 MINUTE)'
    );
    $windowStmt->execute([$userId]);
    if ((int)$windowStmt->fetchColumn() >= KSMEDU_OTP_RESEND_MAX) {
        // Batas per akun tercapai: tidak menerbitkan OTP, balasan tetap generik.
        otp_resend_generic_response();
        exit;
    }

    $resendCount = $pending ? ((int)$pending['resend_count'] + 1) : 1;

    // Payload registrasi tertunda ikut dibawa ke OTP baru: tanpa ini, kirim
    // ulang akan menghapus nama/password yang menunggu verifikasi sehingga
    // user tidak bisa login dengan kredensial yang baru saja ia daftarkan.
    $otp = otp_issue_for_user($pdo, $userId, $resendCount, [
        'name' => (string)($pending['pending_name'] ?? ''),
        'password_hash' => (string)($pending['pending_password_hash'] ?? ''),
        'is_reactivation' => $isReactivation,
    ]);


    if (!otp_send_email($user['email'], (string)$user['name'], $otp)) {
        // Kegagalan transport dicatat ke log saja; klien tetap menerima
        // balasan generik agar tidak membocorkan keberadaan akun.
        error_log('Resend OTP: pengiriman email gagal untuk user id ' . $userId . '.');
    }

    otp_resend_generic_response();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Resend email OTP error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Terjadi kesalahan sistem. Coba lagi nanti.']);
}
