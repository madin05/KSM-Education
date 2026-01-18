<?php
// api/opinions/get.php

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../database/db.php';

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'id required']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT
            o.*,
            uf.url AS file_url,
            uc.url AS cover_url
        FROM opinions o
        LEFT JOIN uploads uf ON o.file_upload_id = uf.id
        LEFT JOIN uploads uc ON o.cover_upload_id = uc.id
        WHERE o.id = ?
    ");

    $stmt->execute([$id]);
    $opinion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$opinion) {
        http_response_code(404);
        throw new Exception('Opinion not found');
    }

    // Increment views
    $updateViews = $pdo->prepare("UPDATE opinions SET views = views + 1 WHERE id = ?");
    $updateViews->execute([$id]);

    echo json_encode([
        'ok' => true,
        'result' => $opinion,
        'opinion' => $opinion
    ]);
} catch (PDOException $e) {
    error_log('Get opinion error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Server error'
    ]);
} catch (Exception $e) {
    error_log('Get opinion error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
?>