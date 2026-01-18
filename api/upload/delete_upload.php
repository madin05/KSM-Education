<?php
// api/uploads/delete.php

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../auth/api_auth_middleware.php';

// Authentication check
$userId = requireAuth();

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'id required']);
        exit;
    }

    $id = (int)$data['id'];

    // Check if upload exists
    $stmt = $pdo->prepare("SELECT filename FROM uploads WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $r = $stmt->fetch();

    if (!$r) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'upload not found']);
        exit;
    }

    $filename = $r['filename'];

    // Check if file is being used by journals or opinions
    $checkJournal = $pdo->prepare("
        SELECT id FROM journals 
        WHERE file_upload_id = ? OR cover_upload_id = ?
    ");
    $checkJournal->execute([$id, $id]);

    $checkOpinion = $pdo->prepare("
        SELECT id FROM opinions 
        WHERE file_upload_id = ? OR cover_upload_id = ?
    ");
    $checkOpinion->execute([$id, $id]);

    if ($checkJournal->rowCount() > 0 || $checkOpinion->rowCount() > 0) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'message' => 'File is still being used by journals or opinions'
        ]);
        exit;
    }

    // Delete physical file
    $path = __DIR__ . '/../../../uploads/' . $filename;
    if (is_file($path)) {
        unlink($path);
        error_log("Deleted file: $path");
    }

    // Delete from database
    $pdo->prepare("DELETE FROM uploads WHERE id = ?")->execute([$id]);

    echo json_encode([
        'ok' => true,
        'id' => $id,
        'message' => 'Upload deleted successfully'
    ]);
} catch (PDOException $e) {
    error_log('Delete upload error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database error'
    ]);
} catch (Exception $e) {
    error_log('Delete upload error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
?>