<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    echo json_encode(['ok' => false, 'message' => 'Only DELETE allowed']);
    exit;
}

parse_str(file_get_contents('php://input'), $del);
$id = isset($del['id']) ? (int)$del['id'] : 0;

if (!$id) {
    echo json_encode(['ok' => false, 'message' => 'id required']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM opinions WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['ok' => true, 'id' => $id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
