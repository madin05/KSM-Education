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

    $stmt = $pdo->prepare("SELECT id, password_hash, name, role, email FROM users WHERE email = ? AND (account_status = 'active' OR account_status IS NULL) LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) { 
        http_response_code(401);
        echo json_encode(['ok'=>false,'message'=>'Email tidak terdaftar!']); 
        exit; 
    }

    if (!password_verify($data['password'], $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['ok'=>false,'message'=>'Password salah!']); 
        exit;
    }


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
} catch (Exception $e) {
    // Jangan bocorkan detail exception (nama tabel, query, path) ke klien.
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'=>false, 
        'message'=>'Terjadi kesalahan sistem. Coba lagi nanti.'
    ]);
}


