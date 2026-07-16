<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo json_encode(['ok' => false, 'message' => 'id required']);
    exit;
}

try {
    //  JOIN dengan uploads table untuk get URL terbaru!
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
        echo json_encode(['ok' => false, 'message' => 'Journal not found']);
        exit;
    }

    //  Increment views
    $updateViews = $pdo->prepare("UPDATE journals SET views = views + 1 WHERE id = ?");
    $updateViews->execute([$id]);

    //  Fix file_url & cover_url — prepend APP_ROOT if not already prefixed
    //  uploads table stores bare paths like /uploads/xxx.pdf
    //  but the browser needs /ksmaja/uploads/xxx.pdf when running in a subfolder
    if (!empty($journal['file_url']) && APP_ROOT && strpos($journal['file_url'], APP_ROOT) !== 0) {
        $journal['file_url'] = APP_ROOT . '/' . ltrim($journal['file_url'], '/');
    }
    if (!empty($journal['cover_url']) && APP_ROOT && strpos($journal['cover_url'], APP_ROOT) !== 0) {
        $journal['cover_url'] = APP_ROOT . '/' . ltrim($journal['cover_url'], '/');
    }

    //  Return with UPDATED file URLs
    echo json_encode([
        'ok' => true,
        'journal' => $journal
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
