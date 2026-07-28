<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db.php';

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        throw new Exception('Invalid opinion ID');
    }

    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.title,
            o.description,
            o.category,
            o.author_name,
            o.file_upload_id,
            o.cover_upload_id,
            o.authors,
            o.tags,
            o.email,
            o.contact,
            o.client_temp_id,
            o.created_at,
            o.updated_at,
            o.views,
            uf.url AS file_url,
            uc.url AS cover_url
        FROM opinions o
        LEFT JOIN uploads uf ON o.file_upload_id = uf.id
        LEFT JOIN uploads uc ON o.cover_upload_id = uc.id
        WHERE o.id = ? AND o.status = 'published'
    ");

    $stmt->execute([$id]);
    $opinion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$opinion) {
        throw new Exception('Opinion not found');
    }

    // Increment views
    $updateViews = $pdo->prepare("UPDATE opinions SET views = views + 1 WHERE id = ? AND status = 'published'");
    $updateViews->execute([$id]);

    echo json_encode([
        'ok' => true,
        'result' => $opinion,
        'opinion' => $opinion
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
