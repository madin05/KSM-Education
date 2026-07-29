<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/telegram_helpers.php';

$user = require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

try {
    $config = telegram_require_config();
    $plainToken = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    $tokenHash = hash('sha256', $plainToken);

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'UPDATE telegram_link_tokens
         SET used_at = CURRENT_TIMESTAMP
         WHERE user_id = ? AND used_at IS NULL'
    );
    $stmt->execute([(int)$user['id']]);
    $stmt = $pdo->prepare(
        'INSERT INTO telegram_link_tokens (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE))'
    );
    $stmt->execute([(int)$user['id'], $tokenHash]);
    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'telegram_url' => 'https://t.me/' . rawurlencode($config['username']) . '?start=buy_' . $plainToken,
        'expires_in' => 600,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Telegram purchase link error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Tautan Telegram belum dapat dibuat. Hubungi admin jika masalah berlanjut.']);
}
