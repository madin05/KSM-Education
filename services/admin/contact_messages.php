<?php

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/jwt_middleware.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'message' => 'Only GET allowed']);
    exit;
}

try {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $status = strtolower(trim((string)($_GET['status'] ?? '')));
    $allowed = ['new', 'read', 'replied', 'closed'];
    if ($status !== '' && !in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Filter status tidak valid.');
    }

    $where = $status === '' ? '' : ' WHERE cm.status = ?';
    $params = $status === '' ? [] : [$status];
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM contact_messages cm' . $where);
    $countStmt->execute($params);

    $stmt = $pdo->prepare(
        'SELECT cm.id, cm.user_id, cm.name, cm.email, cm.subject, cm.message,
                cm.status, cm.admin_reply, cm.replied_at, cm.read_at,
                cm.closed_at, cm.created_at, cm.updated_at,
                admin.name AS replied_by_name
         FROM contact_messages cm
         LEFT JOIN users admin ON admin.id = cm.replied_by' . $where .
        " ORDER BY cm.created_at DESC, cm.id DESC LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);

    echo json_encode([
        'ok' => true,
        'results' => $stmt->fetchAll(),
        'total' => (int)$countStmt->fetchColumn(),
        'limit' => $limit,
        'offset' => $offset,
        'status' => $status,
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Contact inbox error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Gagal memuat kotak masuk kontak.']);
}