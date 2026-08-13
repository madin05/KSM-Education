<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';

$user = require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = is_array($input['ids'] ?? null) ? $input['ids'] : [];
    $ids = array_values(array_unique(array_filter($ids, static function ($id): bool {
        return (is_string($id) || is_int($id)) && ctype_digit((string)$id) && (int)$id > 0;
    })));

    if (!$ids) {
        throw new InvalidArgumentException('Pilih minimal satu riwayat untuk dihapus.');
    }
    if (count($ids) > 100) {
        throw new InvalidArgumentException('Maksimal 100 riwayat dapat dihapus sekaligus.');
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "DELETE FROM token_purchase_requests WHERE user_id = ? AND id IN ({$placeholders})"
    );
    $stmt->execute(array_merge([(int) $user['id']], $ids));

    echo json_encode(['ok' => true, 'deleted' => $stmt->rowCount()]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Token history delete error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Riwayat token belum dapat dihapus.']);
}
