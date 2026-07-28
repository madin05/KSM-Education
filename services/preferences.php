<?php
/** GET/PUT /services/preferences.php — preferences owned by the logged-in user. */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/phase3_helpers.php';

phase3_require_method(['GET', 'PUT']);
$auth_user = require_auth();
$user = phase3_active_user($pdo, (int)$auth_user['id'], 'id, email');
if (!$user) {
    phase3_respond(['ok' => false, 'message' => 'Account is not active.'], 403);
}

$defaults = [
    'theme' => 'light',
    'notification_new_article' => '1',
    'notification_upload_status' => '1',
    'notification_promo' => '0',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    // Seed defaults once so the database remains the source of truth.
    $pdo->beginTransaction();
    try {
        $seed = $pdo->prepare('INSERT INTO user_preferences (user_id, user_email, preference_key, preference_value) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email)');
        foreach ($defaults as $key => $value) {
            $seed->execute([(int)$user['id'], $user['email'], $key, $value]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Preference defaults error: ' . $e->getMessage());
        phase3_respond(['ok' => false, 'message' => 'Gagal memuat preferensi.'], 500);
    }
    $stmt = $pdo->prepare('SELECT preference_key, preference_value FROM user_preferences WHERE user_id = ? ORDER BY preference_key');
    $stmt->execute([(int)$user['id']]);
    $preferences = [];
    foreach ($stmt->fetchAll() as $row) {
        $value = $row['preference_value'];
        $preferences[$row['preference_key']] = in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true)
            ? true
            : (in_array(strtolower((string)$value), ['0', 'false', 'no', 'off'], true) ? false : $value);
    }
    phase3_respond(['ok' => true, 'preferences' => $preferences]);
}

$data = phase3_json_body();
$incoming = $data['preferences'] ?? $data;
if (!is_array($incoming) || !$incoming) {
    phase3_respond(['ok' => false, 'message' => 'preferences harus berupa object yang tidak kosong.'], 422);
}

try {
    $pdo->beginTransaction();
    $upsert = $pdo->prepare('INSERT INTO user_preferences (user_id, user_email, preference_key, preference_value) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_email = VALUES(user_email), preference_value = VALUES(preference_value)');
    foreach ($incoming as $key => $value) {
        $key = (string)$key;
        if ($key === '' || strlen($key) > 100 || !preg_match('/^[A-Za-z0-9_.-]+$/', $key) || (!is_scalar($value) && $value !== null)) {
            throw new InvalidArgumentException('Format preference tidak valid.');
        }
        $value = is_bool($value) ? ($value ? '1' : '0') : ($value === null ? null : (string)$value);
        if ($value !== null && strlen($value) > 1000) {
            throw new InvalidArgumentException('Nilai preference terlalu panjang.');
        }
        $upsert->execute([(int)$user['id'], $user['email'], $key, $value]);
    }
    $pdo->commit();
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    phase3_respond(['ok' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Update preferences error: ' . $e->getMessage());
    phase3_respond(['ok' => false, 'message' => 'Gagal menyimpan preferensi.'], 500);
}

$stmt = $pdo->prepare('SELECT preference_key, preference_value FROM user_preferences WHERE user_id = ? ORDER BY preference_key');
$stmt->execute([(int)$user['id']]);
$preferences = [];
foreach ($stmt->fetchAll() as $row) {
    $value = $row['preference_value'];
    $preferences[$row['preference_key']] = in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true)
        ? true
        : (in_array(strtolower((string)$value), ['0', 'false', 'no', 'off'], true) ? false : $value);
}
phase3_respond(['ok' => true, 'preferences' => $preferences]);