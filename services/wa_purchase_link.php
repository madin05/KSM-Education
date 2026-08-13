<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/wa_helpers.php';

$user = require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

try {
    $config = wa_get_config();
    if (empty($config['bot_number'])) {
        throw new RuntimeException('WA_BOT_NUMBER belum dikonfigurasi di .env.');
    }

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
         VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE))'
    );
    $stmt->execute([(int)$user['id'], $tokenHash]);
    $pdo->commit();

    $prefilledMsg = rawurlencode("Halo Admin KSMedu, saya ingin membeli token. Kode Transaksi: buy_" . $plainToken);
    $waUrl = "https://wa.me/" . $config['bot_number'] . "?text=" . $prefilledMsg;

    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'wa_url' => $waUrl,
        'expires_in' => 900,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('WhatsApp purchase link error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Tautan WhatsApp belum dapat dibuat. ' . $e->getMessage()]);
}
