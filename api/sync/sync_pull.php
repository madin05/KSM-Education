<?php
// api/sync/pull.php

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../database/db.php';

// OPTIONAL AUTH - Uncomment kalau mau require login untuk sync
// require_once __DIR__ . '/../auth/api_auth_middleware.php';
// $userId = requireAuth();

try {
    $since = $_GET['since'] ?? null;

    if ($since) {
        // Get changes since timestamp
        $stmt = $pdo->prepare("
            SELECT j.*, 
                   u.url as file_url, 
                   c.url as cover_url 
            FROM journals j 
            LEFT JOIN uploads u ON u.id = j.file_upload_id 
            LEFT JOIN uploads c ON c.id = j.cover_upload_id 
            WHERE j.updated_at IS NOT NULL 
              AND j.updated_at > ? 
            ORDER BY j.updated_at ASC
        ");
        $stmt->execute([$since]);
    } else {
        // Get all journals (limit 200 for safety)
        $stmt = $pdo->query("
            SELECT j.*, 
                   u.url as file_url, 
                   c.url as cover_url 
            FROM journals j 
            LEFT JOIN uploads u ON u.id = j.file_upload_id 
            LEFT JOIN uploads c ON c.id = j.cover_upload_id 
            ORDER BY j.created_at DESC 
            LIMIT 200
        ");
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'changes' => $rows,
        'count' => count($rows),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    error_log('Sync pull error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database error'
    ]);
} catch (Exception $e) {
    error_log('Sync pull error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
?>