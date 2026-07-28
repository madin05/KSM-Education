<?php
/** POST/PUT /services/update_profile.php — update only the logged-in user. */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/phase3_helpers.php';

phase3_require_method(['POST', 'PUT']);
$auth_user = require_auth();
$data = phase3_json_body();

$name = trim((string)($data['name'] ?? ''));
$hasBio = array_key_exists('bio', $data);
$hasAvatarUrl = array_key_exists('avatar_url', $data);
$bio = $hasBio ? trim((string)$data['bio']) : null;
$avatarUrl = $hasAvatarUrl ? trim((string)$data['avatar_url']) : null;

if ($name === '' || strlen($name) > 200) {
    phase3_respond(['ok' => false, 'message' => 'Nama wajib diisi dan maksimal 200 karakter.'], 422);
}
if ($bio !== null && strlen($bio) > 500) {
    phase3_respond(['ok' => false, 'message' => 'Bio maksimal 500 karakter.'], 422);
}
if ($hasAvatarUrl && $avatarUrl !== '') {
    $parts = parse_url($avatarUrl);
    if (strlen($avatarUrl) > 1024 || !$parts || !isset($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true) || empty($parts['host'])) {
        phase3_respond(['ok' => false, 'message' => 'avatar_url harus berupa URL HTTP/HTTPS yang valid.'], 422);
    }
} elseif ($hasAvatarUrl) {
    $avatarUrl = null;
}

$current = phase3_active_user($pdo, (int)$auth_user['id']);
if (!$current) {
    phase3_respond(['ok' => false, 'message' => 'Account is not active.'], 403);
}
$bio = $hasBio ? ($bio === '' ? null : $bio) : $current['bio'];
$avatarUrl = $hasAvatarUrl ? $avatarUrl : $current['avatar_url'];

$stmt = $pdo->prepare('UPDATE users SET name = ?, bio = ?, avatar_url = ? WHERE id = ? AND account_status = \'active\'');
$stmt->execute([$name, $bio, $avatarUrl, (int)$auth_user['id']]);
$updated = phase3_active_user($pdo, (int)$auth_user['id']);

phase3_respond(['ok' => true, 'user' => phase3_public_user($updated)]);