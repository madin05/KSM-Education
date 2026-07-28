<?php
// sync_pull.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';

// ===== SECURITY: Require authentication =====
require_auth();
$since = isset($_GET['since']) ? $_GET['since'] : null; // expect 'YYYY-mm-dd HH:MM:SS' or ISO

$select = "
    SELECT j.id, j.title, j.abstract, j.authors, j.tags, j.pengurus,
           j.email, j.contact, j.volume, j.views, j.client_temp_id,
           j.client_updated_at, j.created_at, j.updated_at,
           u.url AS file_url, c.url AS cover_url
    FROM journals j
    LEFT JOIN uploads u ON u.id = j.file_upload_id
    LEFT JOIN uploads c ON c.id = j.cover_upload_id
    WHERE j.status = 'published'
";

if ($since) {
    $stmt = $pdo->prepare($select . " AND j.updated_at IS NOT NULL AND j.updated_at > ? ORDER BY j.updated_at ASC");
    $stmt->execute([$since]);
} else {
    $stmt = $pdo->query($select . " ORDER BY j.created_at DESC LIMIT 200");
}
$rows = $stmt->fetchAll();
echo json_encode(['ok'=>true,'changes'=>$rows]);
