<?php
// api/opinions/list.php

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../database/db.php';

// OPTIONAL AUTH - Uncomment kalau mau require login di masa depan
// require_once __DIR__ . '/../auth/api_auth_middleware.php';
// $userId = requireAuth();

try {
    // Get parameters
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $category = isset($_GET['category']) ? trim($_GET['category']) : 'all';

    // Validate pagination
    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);

    error_log("List opinions - limit: $limit, offset: $offset, category: $category");

    // Build query
    $sql = "
        SELECT
            o.*,
            uf.url AS file_url,
            uc.url AS cover_url
        FROM opinions o
        LEFT JOIN uploads uf ON o.file_upload_id = uf.id
        LEFT JOIN uploads uc ON o.cover_upload_id = uc.id
    ";

    $params = [];
    if ($category && $category !== 'all') {
        $sql .= " WHERE o.category = ?";
        $params[] = $category;
    }

    $sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $opinions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Found " . count($opinions) . " opinions");

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM opinions";
    if ($category && $category !== 'all') {
        $countSql .= " WHERE category = ?";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$category]);
    } else {
        $countStmt = $pdo->query($countSql);
    }

    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    error_log("Total opinions in DB: $total");

    echo json_encode([
        'ok' => true,
        'results' => $opinions,
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ]);

} catch (PDOException $e) {
    error_log('List opinions error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('List opinions error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?>