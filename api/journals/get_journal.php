<?php
// api/journals/get.php

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
    // JOIN dengan uploads table untuk get URL terbaru
    $stmt = $pdo->prepare("
        SELECT
            j.*,
            f.url as file_url,
            c.url as cover_url
        FROM journals j
        LEFT JOIN uploads f ON j.file_upload_id = f.id
        LEFT JOIN uploads c ON j.cover_upload_id = c.id
        WHERE j.id = ?
    ");

    $stmt->execute([$id]);
    $journal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$journal) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Journal not found']);
        exit;
    }

    // Increment views
    $updateViews = $pdo->prepare("UPDATE journals SET views = views + 1 WHERE id = ?");
    $updateViews->execute([$id]);

    // Return with UPDATED file URLs
    echo json_encode([
        'ok' => true,
        'journal' => $journal
    ]);
} catch (PDOException $e) {
    error_log('Get journal error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error']);
} catch (Exception $e) {
    error_log('Get journal error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
?>