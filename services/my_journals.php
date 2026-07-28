<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/submission_helpers.php';

$auth_user = require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'message' => 'Only GET allowed']);
    exit;
}

try {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $statuses = ['draft', 'pending', 'published', 'rejected'];
    $status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : null;
    if ($status !== null && !in_array($status, $statuses, true)) {
        throw new InvalidArgumentException('Filter status tidak valid.');
    }

    $where = 'j.user_id = ?';
    $params = [(int)$auth_user['id']];
    if ($status !== null) {
        $where .= ' AND j.status = ?';
        $params[] = $status;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM journals j WHERE {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT j.id, j.title, j.abstract, j.authors, j.tags, j.pengurus,
                j.email, j.contact, j.volume, j.status, j.rejection_reason,
                j.created_at, j.updated_at, j.reviewed_at,
                fu.url AS file_url, cu.url AS cover_url
         FROM journals j
         LEFT JOIN uploads fu ON fu.id = j.file_upload_id
         LEFT JOIN uploads cu ON cu.id = j.cover_upload_id
         WHERE {$where}
         ORDER BY j.created_at DESC, j.id DESC
         LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $results = array_map('submission_decode_lists', $stmt->fetchAll());

    echo json_encode(['ok' => true, 'results' => $results, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
} catch (Throwable $e) {
    submission_error($e);
}