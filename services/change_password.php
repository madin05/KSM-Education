<?php
/** POST /services/change_password.php — change the logged-in user's password. */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/phase3_helpers.php';

phase3_require_method(['POST']);
$auth_user = require_auth();
$data = phase3_json_body();

$oldPassword = (string)($data['old_password'] ?? '');
$newPassword = (string)($data['new_password'] ?? '');
if ($oldPassword === '' || $newPassword === '') {
    phase3_respond(['ok' => false, 'message' => 'Password lama dan password baru wajib diisi.'], 422);
}
if (strlen($newPassword) < 8 || strlen($newPassword) > 255) {
    phase3_respond(['ok' => false, 'message' => 'Password baru harus 8 sampai 255 karakter.'], 422);
}

$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? AND account_status = 'active' LIMIT 1");
$stmt->execute([(int)$auth_user['id']]);
$user = $stmt->fetch();
if (!$user) {
    phase3_respond(['ok' => false, 'message' => 'Account is not active.'], 403);
}
if (!password_verify($oldPassword, $user['password_hash'])) {
    phase3_respond(['ok' => false, 'message' => 'Password lama salah.'], 422);
}
if (password_verify($newPassword, $user['password_hash'])) {
    phase3_respond(['ok' => false, 'message' => 'Password baru harus berbeda dari password lama.'], 422);
}

try {
    $pdo->beginTransaction();
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ? AND account_status = 'active'");
    $update->execute([$newHash, (int)$auth_user['id']]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Account update failed');
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Change password error: ' . $e->getMessage());
    phase3_respond(['ok' => false, 'message' => 'Gagal mengubah password.'], 500);
}

phase3_respond(['ok' => true, 'message' => 'Password berhasil diubah.']);