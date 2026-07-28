<?php
/** POST /services/delete_account.php — soft-delete the logged-in account. */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/phase3_helpers.php';

phase3_require_method(['POST']);
$auth_user = require_auth();
$data = phase3_json_body();
$password = (string)($data['old_password'] ?? $data['password'] ?? '');
if ($password === '') {
    phase3_respond(['ok' => false, 'message' => 'Password lama wajib diisi sebagai konfirmasi.'], 422);
}

$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? AND account_status = 'active' LIMIT 1");
$stmt->execute([(int)$auth_user['id']]);
$user = $stmt->fetch();
if (!$user) {
    phase3_respond(['ok' => false, 'message' => 'Account is not active.'], 403);
}
if (!password_verify($password, $user['password_hash'])) {
    phase3_respond(['ok' => false, 'message' => 'Password lama salah.'], 422);
}

try {
    $pdo->beginTransaction();
    // Do not touch journals/opinions: published content and its ownership
    // remain intact.  The Phase 1 SET NULL rules apply only to hard deletes.
    $update = $pdo->prepare("UPDATE users SET account_status = 'deleted', deleted_at = NOW() WHERE id = ? AND account_status = 'active'");
    $update->execute([(int)$auth_user['id']]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Account update failed');
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Delete account error: ' . $e->getMessage());
    phase3_respond(['ok' => false, 'message' => 'Gagal menonaktifkan akun.'], 500);
}

// Revoke the current access token when JWT authentication is used.
$token = get_bearer_token();
if ($token) {
    $payload = jwt_decode($token, JWT_SECRET);
    if ($payload && isset($payload['jti'], $payload['exp'])) {
        blacklist_token($payload['jti'], (int)$payload['exp']);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
session_destroy();

phase3_respond(['ok' => true, 'message' => 'Akun berhasil dinonaktifkan.']);