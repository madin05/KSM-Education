<?php
/**
 * POST /services/auth_login.php — login area pengguna.
 *
 * Endpoint ini SELALU bekerja pada konteks 'user' sehingga login di sini
 * tidak pernah menyentuh session panel admin (cookie KSMEDUADMSESS) dan
 * token yang diterbitkan hanya sah untuk area user.
 */

// Harus dideklarasikan sebelum auth_context.php dipakai untuk resolve konteks.
define('KSMEDU_FORCE_CONTEXT', 'user');

require_once __DIR__ . '/auth_context.php';
ksmedu_session_start(KSMEDU_CTX_USER);
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_helper.php';
require_once __DIR__ . '/login_guard.php';



// Status code harus konsisten dengan hasil (422/401/405/500), bukan selalu 200.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok'=>false,'message'=>'Metode tidak diizinkan.']);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw,true);
    if (!$data || empty($data['email']) || empty($data['password'])) { 
        http_response_code(422);
        echo json_encode(['ok'=>false,'message'=>'Data input tidak valid!']); 
        exit; 
    }


    $email = trim($data['email']);
    $password = $data['password'];

    // Brute-force guard: dievaluasi SEBELUM query user dan sebelum
    // password_verify(), sehingga request yang sudah terkunci tidak menghabiskan
    // biaya bcrypt maupun query database.
    $retryAfter = login_guard_check($pdo, 'user', $email);
    if ($retryAfter > 0) {
        login_guard_reject($retryAfter);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, password_hash, name, role, email, email_verified_at FROM users WHERE email = ? AND (account_status = 'active' OR account_status IS NULL) LIMIT 1");


    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Kredensial salah selalu memakai satu pesan yang sama sehingga klien tidak
    // bisa membedakan "email tidak terdaftar" dari "password salah"
    // (mencegah user enumeration).
    $invalidCredentials = 'Email atau password salah!';

    if (!$user) {
        // Tetap jalankan satu password_verify() terhadap hash dummy agar waktu
        // respons untuk email tidak terdaftar mirip dengan email terdaftar
        // (mengurangi kebocoran lewat timing).
        password_verify(
            $password,
            '$2y$10$usesomesillystringforeasyverificationabcdefghijklmnopqrstuv'
        );
        login_guard_record($pdo, 'user', $email, false);
        login_guard_delay(login_guard_current_failures($pdo, 'user', $email));
        http_response_code(401);
        echo json_encode(['ok'=>false,'message'=>$invalidCredentials]);
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        login_guard_record($pdo, 'user', $email, false);
        login_guard_delay(login_guard_current_failures($pdo, 'user', $email));
        http_response_code(401);
        echo json_encode(['ok'=>false,'message'=>$invalidCredentials]);
        exit;
    }



    // Email OTP: akun yang belum terverifikasi tidak boleh login.
    // Kolom email_verified_at bisa NULL untuk akun lama yang di-backfill
    // migrasi 009, jadi akun lama tetap dapat login seperti sebelumnya.
    if (array_key_exists('email_verified_at', $user) && empty($user['email_verified_at'])) {
        http_response_code(403);
        echo json_encode([
            'ok'=>false,
            'needs_verification'=>true,
            'message'=>'Silakan verifikasi email terlebih dahulu.'
        ]);
        exit;
    }



    // Kredensial benar: hapus riwayat kegagalan agar pengguna sah tidak
    // membawa sisa hitungan lockout, lalu catat login sukses untuk audit.
    login_guard_clear($pdo, 'user', $email);
    login_guard_record($pdo, 'user', $email, true);

    // Set PHP Session (backward compatibility)
    // Reset total isi session lama sebelum memasang identitas baru.
    // Tanpa ini, sisa data sesi admin (mis. role=admin) bisa bertahan dan
    // membuat halaman user memakai identitas admin.
    $_SESSION = [];
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];

    // Generate JWT Tokens
    $accessToken = generate_access_token($user);
    $refreshToken = generate_refresh_token($user);
    
    echo json_encode([
        'ok'=>true,
        'user'=>[
            'id'=>$user['id'],
            'name'=>$user['name'],
            'role'=>$user['role']
        ],
        'access_token' => $accessToken['token'],
        'refresh_token' => $refreshToken['token'],
        'expires_in' => $accessToken['expires_in']
    ]);
} catch (LoginGuardUnavailableException $e) {
    // Fail closed: guard tidak dapat dievaluasi/dicatat, jadi login ditolak
    // sementara daripada membuka pintu tanpa proteksi brute-force.
    error_log('Login guard unavailable: ' . $e->getMessage());
    login_guard_unavailable();
} catch (Exception $e) {
    // Jangan bocorkan detail exception (nama tabel, query, path) ke klien.
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'=>false, 
        'message'=>'Terjadi kesalahan sistem. Coba lagi nanti.'
    ]);
}


