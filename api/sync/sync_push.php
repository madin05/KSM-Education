
<?php
// api/sync/push.php

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../auth/api_auth_middleware.php';

// AUTH REQUIRED - Creating data requires authentication
$userId = requireAuth();

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || !isset($data['changes'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid payload']);
        exit;
    }

    $applied = [];

    foreach ($data['changes'] as $chg) {
        // Struktur: { type: 'journal', action:'create', payload: {...}, client_id: 'tmp-1' }
        if ($chg['type'] === 'journal' && $chg['action'] === 'create') {
            $p = $chg['payload'];

            // Find upload IDs from URLs
            $file_upload_id = null;
            $cover_upload_id = null;

            if (!empty($p['fileUrl'])) {
                $s = $pdo->prepare("SELECT id FROM uploads WHERE url = ? LIMIT 1");
                $s->execute([$p['fileUrl']]);
                $r = $s->fetch();
                if ($r) $file_upload_id = $r['id'];
            }

            if (!empty($p['coverUrl'])) {
                $s = $pdo->prepare("SELECT id FROM uploads WHERE url = ? LIMIT 1");
                $s->execute([$p['coverUrl']]);
                $r = $s->fetch();
                if ($r) $cover_upload_id = $r['id'];
            }

            $authors = isset($p['authors']) ? json_encode($p['authors']) : null;
            $tags = isset($p['tags']) ? json_encode($p['tags']) : null;

            // Insert journal
            $stmt = $pdo->prepare("
                INSERT INTO journals 
                (title, abstract, file_upload_id, cover_upload_id, authors, tags, client_temp_id, client_updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $p['title'] ?? '',
                $p['abstract'] ?? null,
                $file_upload_id,
                $cover_upload_id,
                $authors,
                $tags,
                $chg['client_id'] ?? null,
                $p['client_updated_at'] ?? null
            ]);

            $server_id = $pdo->lastInsertId();

            $applied[] = [
                'client_id' => $chg['client_id'] ?? null,
                'status' => 'ok',
                'server_id' => $server_id
            ];

        } else {
            $applied[] = [
                'client_id' => $chg['client_id'] ?? null,
                'status' => 'unsupported'
            ];
        }
    }

    echo json_encode([
        'ok' => true,
        'applied' => $applied,
        'count' => count($applied)
    ]);

} catch (PDOException $e) {
    error_log('Sync push error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database error'
    ]);
} catch (Exception $e) {
    error_log('Sync push error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
?>