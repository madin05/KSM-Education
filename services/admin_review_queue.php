<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/submission_helpers.php';

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
    $allowedStatuses = ['pending', 'published', 'rejected'];
    $status = strtolower(trim((string)($_GET['status'] ?? 'pending')));
    if (!in_array($status, $allowedStatuses, true)) {
        throw new InvalidArgumentException('Filter status review tidak valid.');
    }

    // Opini memakai alur review yang sama dengan jurnal (pending -> published).
    $type = strtolower(trim((string)($_GET['type'] ?? 'journal')));
    if (!in_array($type, ['journal', 'opinion'], true)) {
        throw new InvalidArgumentException('Parameter type harus journal atau opinion.');
    }

    if ($type === 'opinion') {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM opinions WHERE status = ?');
        $countStmt->execute([$status]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT o.id, o.user_id, o.title, o.description AS abstract, o.category,
                    o.author_name, o.tags, o.email, o.contact, o.status,
                    o.rejection_reason, o.views, o.created_at, o.updated_at, o.reviewed_at,
                    u.name AS owner_name, u.email AS owner_email,
                    reviewer.name AS reviewer_name,
                    fu.url AS file_url, cu.url AS cover_url
             FROM opinions o
             LEFT JOIN users u ON u.id = o.user_id
             LEFT JOIN users reviewer ON reviewer.id = o.reviewed_by
             LEFT JOIN uploads fu ON fu.id = o.file_upload_id
             LEFT JOIN uploads cu ON cu.id = o.cover_upload_id
             WHERE o.status = ?
             ORDER BY o.created_at ASC, o.id ASC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute([$status]);
        $results = array_map(static function (array $row): array {
            // Decode dulu kolom JSON (tags), baru bentuk authors sebagai array
            // supaya submission_decode_lists tidak menerima nilai array.
            $row = submission_decode_lists($row);
            $row['authors'] = $row['author_name'] !== null && $row['author_name'] !== ''
                ? [$row['author_name']]
                : [];
            return $row;
        }, $stmt->fetchAll());


        echo json_encode([
            'ok' => true,
            'results' => $results,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'status' => $status,
            'type' => $type,
        ]);
        exit;
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM journals WHERE status = ?');
    $countStmt->execute([$status]);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT j.id, j.user_id, j.title, j.abstract, j.authors, j.tags,
                j.pengurus, j.email, j.contact, j.volume, j.status,
                j.rejection_reason, j.created_at, j.updated_at, j.reviewed_at,
                u.name AS owner_name, u.email AS owner_email,
                reviewer.name AS reviewer_name,
                fu.url AS file_url, cu.url AS cover_url
         FROM journals j
         LEFT JOIN users u ON u.id = j.user_id
         LEFT JOIN users reviewer ON reviewer.id = j.reviewed_by
         LEFT JOIN uploads fu ON fu.id = j.file_upload_id
         LEFT JOIN uploads cu ON cu.id = j.cover_upload_id
         WHERE j.status = ?
         ORDER BY j.created_at ASC, j.id ASC
         LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute([$status]);
    $results = array_map('submission_decode_lists', $stmt->fetchAll());

    echo json_encode([
        'ok' => true,
        'results' => $results,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'status' => $status,
        'type' => $type,
    ]);
} catch (Throwable $e) {
    submission_error($e);
}