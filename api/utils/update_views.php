<?php
// api/utils/update_views.php

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../../database/db.php';

// PUBLIC ACCESS - User bisa nambah views tanpa login
// OPTIONAL AUTH - Uncomment kalau mau require login
// require_once __DIR__ . '/../auth/api_auth_middleware.php';
// $userId = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $table = $data['table'] ?? '';
    $id = isset($data['id']) ? (int)$data['id'] : 0;

    // Validate table name (prevent SQL injection)
    if (!in_array($table, ['journals', 'opinions'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid table']);
        exit;
    }

    if (!$id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'id required']);
        exit;
    }

    // Increment views
    $stmt = $pdo->prepare("UPDATE $table SET views = views + 1 WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'ok' => true,
        'message' => 'Views updated',
        'table' => $table,
        'id' => $id
    ]);

} catch (PDOException $e) {
    error_log('Update views error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log('Update views error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
?>