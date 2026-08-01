<?php
/**
 * GET/POST /services/auth_logout.php
 * 
 * Logs out the user by:
 * 1. Blacklisting the current JWT token (if present)
 * 2. Destroying the PHP session
 */

require_once __DIR__ . '/auth_context.php';

// Logout hanya menghancurkan session pada konteks pemanggil (admin ATAU user),
// sehingga logout dari panel admin tidak mematikan sesi pengguna di tab lain.
$ksmedu_ctx = ksmedu_session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_helper.php';


// === BLACKLIST JWT TOKEN (if provided) ===
$token = get_bearer_token();
if ($token) {
    $payload = jwt_decode($token, JWT_SECRET);
    if ($payload && isset($payload['jti'], $payload['exp'])) {
        blacklist_token($payload['jti'], $payload['exp']);
    }
}

// Also blacklist refresh token if sent in body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if ($data && !empty($data['refresh_token'])) {
    $refreshPayload = jwt_decode($data['refresh_token'], JWT_SECRET);
    if ($refreshPayload && isset($refreshPayload['jti'], $refreshPayload['exp'])) {
        blacklist_token($refreshPayload['jti'], $refreshPayload['exp']);
    }
}

// === DESTROY PHP SESSION (hanya konteks ini) ===
ksmedu_session_destroy($ksmedu_ctx);


if (isset($_GET['redirect'])) {
    $redirect = $_GET['redirect'];
    // SECURITY: Only allow same-origin redirects (prevent open redirect attacks)
    $parsed = parse_url($redirect);
    $serverHost = $_SERVER['HTTP_HOST'] ?? '';
    
    $isSameOrigin = (
        (!isset($parsed['host'])) ||
        ($parsed['host'] === $serverHost)
    );
    
    if ($isSameOrigin && strpos($redirect, '//') !== 0) {
        header('Location: ' . $redirect);
    } else {
        header('Location: ' . APP_ROOT . '/user/dashboard_user.php');
    }
    exit;
}

echo json_encode(['ok'=>true, 'message'=>'Logged out successfully']);
