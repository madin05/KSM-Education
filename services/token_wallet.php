<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/token_service.php';

$user = require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only GET allowed']);
    exit;
}

try {
    echo json_encode([
        'ok' => true,
        'wallet' => token_get_wallet($pdo, (int)$user['id']),
        'history' => token_get_history($pdo, (int)$user['id']),
    ]);
} catch (Throwable $e) {
    error_log('Token wallet read error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Data token belum dapat dimuat.']);
}