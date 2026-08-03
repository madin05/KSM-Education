<?php
// api/auth_register.php
// Registrasi selalu milik area pengguna: jangan pernah menyentuh sesi admin.
define('KSMEDU_FORCE_CONTEXT', 'user');
require_once __DIR__ . '/auth_context.php';
ksmedu_session_start(KSMEDU_CTX_USER);
require_once __DIR__ . '/db.php';

require_once __DIR__ . '/jwt_helper.php';
require_once __DIR__ . '/email_otp_helpers.php';


// Set header JSON
header('Content-Type: application/json; charset=utf-8');

// Get raw POST data
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Status code harus mencerminkan hasil: klien (fetch/axios) dan reverse proxy
// memakai res.ok / res.status. Sebelumnya semua kegagalan validasi dibalas
// HTTP 200 sehingga error tidak terdeteksi oleh pemanggil yang hanya
// memeriksa status code.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

if (!$data || empty($data['name']) || empty($data['email']) || empty($data['password'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Semua field wajib diisi!']);
    exit;
}


$name = trim($data['name']);
$email = trim($data['email']);
$password = $data['password'];

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Format email tidak valid!']);
    exit;
}

// Check password length
if (strlen($password) < 6) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Password minimal 6 karakter!']);
    exit;
}


/**
 * Balasan tunggal untuk semua jalur registrasi yang "diterima": akun baru
 * berhasil dibuat, email sudah terdaftar, maupun pengiriman email gagal.
 * Bentuk respons dibuat identik agar pemanggil tidak bisa menyimpulkan apakah
 * sebuah email sudah terdaftar (user enumeration).
 */
function register_generic_response(string $email): void
{
    echo json_encode([
        'ok' => true,
        'requires_verification' => true,
        'email_sent' => true,
        'message' => 'Registrasi diproses. Kode OTP telah dikirim ke email Anda jika email dapat digunakan.',
        'email' => $email
    ]);
}

try {
    // Email yang sudah terdaftar TIDAK diberi tahu ke klien: seluruh jalur di
    // bawah membalas register_generic_response() yang identik dengan jalur akun
    // baru, sehingga klien tidak bisa menyimpulkan status sebuah email.
    $stmt = $pdo->prepare(
        "SELECT id, name, email_verified_at, account_status
         FROM users WHERE email = ? LIMIT 1"
    );
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $existingId = (int)$existing['id'];
        $status = (string)($existing['account_status'] ?? 'active');
        $isVerified = !empty($existing['email_verified_at']);

        // Akun aktif yang SUDAH terverifikasi: tidak ada yang dilakukan. Jalur
        // "lupa password" adalah satu-satunya cara mengganti kredensial akun
        // yang sudah hidup, bukan endpoint registrasi.
        if ($isVerified && $status !== 'deleted') {
            error_log('Registration attempt for existing verified email (generic response returned).');
            register_generic_response($email);
            exit;
        }

        // Sisa jalur = akun belum terverifikasi ATAU akun yang di-soft-delete.
        // Keduanya berhak menerima OTP baru, tetapi nama/password dari
        // percobaan ini DITAHAN di email_verifications dan baru diterapkan
        // setelah OTP terbukti benar. Tanpa penahanan ini, siapa pun yang tahu
        // sebuah email bisa menimpa kredensial akun tanpa akses ke inbox.
        $isReactivation = ($status === 'deleted');

        // Registrasi ulang memakai kuota rate limit yang sama dengan tombol
        // "Kirim Ulang OTP" supaya endpoint ini tidak menjadi jalur pintas
        // untuk membanjiri inbox orang lain.
        if (otp_ip_rate_limited($pdo, 'resend', KSMEDU_OTP_IP_MAX_RESEND)) {
            error_log('Registration re-attempt blocked by IP rate limit.');
            register_generic_response($email);
            exit;
        }
        otp_record_ip_attempt($pdo, 'resend');

        // Cooldown 60 detik dihitung dari OTP terakhir yang diterbitkan,
        // sama seperti resend_email_otp.php.
        $lastStmt = $pdo->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, created_at, CURRENT_TIMESTAMP) AS age_seconds
             FROM email_verifications WHERE user_id = ? ORDER BY id DESC LIMIT 1'
        );
        $lastStmt->execute([$existingId]);
        $lastAge = $lastStmt->fetchColumn();

        if ($lastAge !== false && (int)$lastAge < KSMEDU_OTP_RESEND_COOLDOWN) {
            register_generic_response($email);
            exit;
        }

        $pdo->beginTransaction();
        $otp = otp_issue_for_user($pdo, $existingId, 0, [
            'name' => $name,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'is_reactivation' => $isReactivation,
        ]);
        $pdo->commit();

        // Email dikirim ke alamat pemilik akun (bukan ke nama baru): OTP hanya
        // bisa dibaca oleh pemegang inbox tersebut.
        if (!otp_send_email($email, (string)$existing['name'], $otp)) {
            error_log('Registration re-attempt: pengiriman email OTP gagal untuk user id ' . $existingId . '.');
        }

        register_generic_response($email);
        exit;
    }

    $pdo->beginTransaction();


    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'user')");
    $stmt->execute([$name, $email, $hash]);

    $userId = $pdo->lastInsertId();

    // Create the wallet in the same transaction as the account. The token
    // service also keeps this operation idempotent for legacy accounts.
    $walletStmt = $pdo->prepare("INSERT INTO user_token_wallets (user_id, balance) VALUES (?, 0)");
    $walletStmt->execute([(int)$userId]);

    // Akun tersimpan sebagai BELUM TERVERIFIKASI (users.email_verified_at NULL)
    // dan hanya bisa login setelah OTP dikonfirmasi.
    $otp = otp_issue_for_user($pdo, (int)$userId);

    $pdo->commit();

    // Belum ada sesi/token yang diterbitkan di sini: user harus verifikasi OTP
    // lebih dulu. Sisa sesi lama tetap dibersihkan seperti perilaku sebelumnya.
    $_SESSION = [];
    session_regenerate_id(true);

    if (!otp_send_email($email, $name, $otp)) {
        // Kegagalan transport hanya dicatat ke log; balasan tetap sama dengan
        // jalur sukses agar tidak menjadi kanal informasi tambahan.
        error_log('Registration: pengiriman email OTP gagal untuk user id ' . (int)$userId . '.');
    }

    register_generic_response($email);



} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Terjadi kesalahan sistem. Coba lagi nanti.']);

}
