<?php
// api/auth_register.php
// Registrasi selalu milik area pengguna: jangan pernah menyentuh sesi admin.
define('KSMEDU_FORCE_CONTEXT', 'user');
require_once __DIR__ . '/auth_context.php';
ksmedu_session_start(KSMEDU_CTX_USER);
require_once __DIR__ . '/db.php';

require_once __DIR__ . '/jwt_helper.php';

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


try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'Email sudah terdaftar!']);
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

    $pdo->commit();

    // Set PHP Session (backward compatibility)
    // Reset session lama (mis. sisa sesi admin) sebelum memasang akun baru.
    $_SESSION = [];
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = 'user';
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;

    // Generate JWT Tokens
    $userData = [
        'id' => $userId,
        'name' => $name,
        'email' => $email,
        'role' => 'user'
    ];
    $accessToken = generate_access_token($userData);
    $refreshToken = generate_refresh_token($userData);

    echo json_encode([
        'ok' => true, 
        'message' => 'Registrasi berhasil!',
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'role' => 'user'
        ],
        'access_token' => $accessToken['token'],
        'refresh_token' => $refreshToken['token'],
        'expires_in' => $accessToken['expires_in']
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Terjadi kesalahan sistem. Coba lagi nanti.']);

}
