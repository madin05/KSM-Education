<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/token_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

$raw = (string)file_get_contents('php://input');
$timestamp = (string)($_SERVER['HTTP_X_KSM_TIMESTAMP'] ?? '');
$signature = (string)($_SERVER['HTTP_X_KSM_SIGNATURE'] ?? '');
$secret = trim((string)get_env_var('TELEGRAM_INTERNAL_SECRET', ''));

if ($secret === '' || strlen($secret) < 32 || !ctype_digit($timestamp) || abs(time() - (int)$timestamp) > 300) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Invalid or expired internal authorization.']);
    exit;
}

$expected = hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Invalid internal signature.']);
    exit;
}

$data = json_decode($raw, true);
$requestId = (int)($data['request_id'] ?? 0);
$adminTelegramId = (int)($data['admin_telegram_id'] ?? 0);
if ($requestId < 1 || $adminTelegramId < 1) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid approval payload.']);
    exit;
}

try {
    echo json_encode(token_approve_purchase($pdo, $requestId, $adminTelegramId));
} catch (RuntimeException $e) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Internal add token error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Token belum dapat ditambahkan.']);
}
