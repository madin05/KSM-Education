<?php
/**
 * POST /services/auth_admin_login.php
 *
 * Login khusus panel admin. Berbeda dari auth_login.php:
 *   - hanya menerima akun dengan role = 'admin'
 *   - session tidak dibuat sama sekali jika role bukan admin
 *
 * Response sama seperti auth_login.php agar TokenManager di sisi klien
 * dapat dipakai tanpa perubahan.
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_helper.php';

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data) || empty($data['email']) || empty($data['password'])) {
        echo json_encode(['ok' => false, 'message' => 'Email dan password wajib diisi!']);
        exit;
    }

    $email = trim((string)$data['email']);

    $stmt = $pdo->prepare(
        "SELECT id, name, email, role, password_hash
         FROM users
         WHERE email = ? AND (account_status = 'active' OR account_status IS NULL)
         LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Pesan generik: jangan bocorkan apakah email admin terdaftar atau tidak.
    $genericError = ['ok' => false, 'message' => 'Email atau password admin salah!'];

    if (!$user || !password_verify((string)$data['password'], (string)$user['password_hash'])) {
        http_response_code(401);
        echo json_encode($genericError);
        exit;
    }

    if (($user['role'] ?? 'user') !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'message' => 'Akun ini bukan administrator. Gunakan halaman login pengguna.',
            'code' => 'NOT_ADMIN',
        ]);
        exit;
    }

    // Session PHP (dipakai guard halaman admin & endpoint legacy)
    // Buang sisa sesi sebelumnya (mis. sesi user biasa) supaya tidak ada
    // data identitas campuran di dalam satu session.
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['email']   = $user['email'];

    $accessToken  = generate_access_token($user);
    $refreshToken = generate_refresh_token($user);

    echo json_encode([
        'ok' => true,
        'user' => [
            'id'    => (int)$user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ],
        'access_token'  => $accessToken['token'],
        'refresh_token' => $refreshToken['token'],
        'expires_in'    => $accessToken['expires_in'],
    ]);
} catch (Throwable $e) {
    error_log('Admin login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Terjadi kesalahan server saat login.']);
}
