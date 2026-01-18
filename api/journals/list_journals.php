<?php
// api/journals/list.php

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../database/db.php';

// OPTIONAL AUTH - Uncomment kalau mau require login di masa depan
// require_once __DIR__ . '/../auth/api_auth_middleware.php';
// $userId = requireAuth();

try {
    // Pagination parameters
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    // Validate pagination
    $limit = max(1, min(100, $limit)); // Min 1, max 100
    $offset = max(0, $offset);

    // Get journals with pagination
    $stmt = $pdo->prepare("
        SELECT
            id, title, abstract, authors, email, contact, pengurus, volume, tags, views, created_at,
            file_upload_id, cover_upload_id
        FROM journals
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");

    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get file URLs and cover URLs
    foreach ($rows as &$row) {
        if (!empty($row['file_upload_id'])) {
            $fileStmt = $pdo->prepare("SELECT url FROM uploads WHERE id = ?");
            $fileStmt->execute([$row['file_upload_id']]);
            $file = $fileStmt->fetch(PDO::FETCH_ASSOC);
            $row['file_url'] = $file ? $file['url'] : '';
        } else {
            $row['file_url'] = '';
        }

        if (!empty($row['cover_upload_id'])) {
            $coverStmt = $pdo->prepare("SELECT url FROM uploads WHERE id = ?");
            $coverStmt->execute([$row['cover_upload_id']]);
            $cover = $coverStmt->fetch(PDO::FETCH_ASSOC);
            $row['cover_url'] = $cover ? $cover['url'] : '';
        } else {
            $row['cover_url'] = '';
        }

        // Set default untuk pengurus jika NULL
        $row['pengurus'] = $row['pengurus'] ? json_decode($row['pengurus'], true) : [];

        // Remove upload IDs (security)
        unset($row['file_upload_id']);
        unset($row['cover_upload_id']);
    }

    echo json_encode(['ok' => true, 'results' => $rows]);

} catch (PDOException $e) {
    error_log('List journals error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database error',
        'error' => 'Server error'
    ]);
} catch (Exception $e) {
    error_log('List journals error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
        'error' => 'Database error'
    ]);
}
?>