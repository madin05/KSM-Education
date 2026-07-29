<?php
// delete_upload.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';

header('Content-Type: application/json; charset=utf-8');

$auth_user = require_auth();

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'], true)) {
    http_response_code(405);
    header('Allow: POST, DELETE');
    echo json_encode(['ok' => false, 'message' => 'Only POST or DELETE allowed']);
    exit;
}

$data = $_SERVER['REQUEST_METHOD'] === 'DELETE'
    ? $_GET
    : (json_decode(file_get_contents('php://input'), true) ?: $_POST);
if (!isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'id required']);
    exit;
}

$id = (int)$data['id'];
if ($id < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'id must be a positive integer']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id, user_id, filename FROM uploads WHERE id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$id]);
    $upload = $stmt->fetch();
    if (!$upload) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'upload not found']);
        exit;
    }

    if ((int)$upload['user_id'] !== (int)$auth_user['id'] && ($auth_user['role'] ?? '') !== 'admin') {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Anda tidak memiliki akses ke upload ini.']);
        exit;
    }

    $referenceStmt = $pdo->prepare(
        'SELECT 1 FROM journals WHERE file_upload_id = ? OR cover_upload_id = ?
         UNION ALL
         SELECT 1 FROM opinions WHERE file_upload_id = ? OR cover_upload_id = ?
         UNION ALL
         SELECT 1 FROM token_purchase_requests WHERE proof_file_id = ?
         LIMIT 1'
    );
    $referenceStmt->execute([$id, $id, $id, $id, $id]);
    if ($referenceStmt->fetchColumn()) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'Upload sudah digunakan dan tidak dapat dihapus.']);
        exit;
    }

    $pdo->prepare('DELETE FROM uploads WHERE id = ?')->execute([$id]);
    $pdo->commit();

    $path = rtrim(UPLOAD_DIR_ABS, '/\\') . '/' . basename((string)$upload['filename']);
    if (is_file($path) && !unlink($path)) {
        error_log('Cannot remove orphaned upload file: ' . $path);
    }

    echo json_encode(['ok' => true, 'id' => $id]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Upload deletion failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Upload tidak dapat dihapus.']);
}
