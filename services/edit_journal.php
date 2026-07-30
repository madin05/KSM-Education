<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';

// ===== JWT AUTH: Admin Only =====
// Legacy endpoint. The frontend uses update_journal.php (admin) and
// update_my_journal.php (owner); this file is kept for backward
// compatibility but must never be reachable without an admin token.
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input) || !isset($input['id'])) {
        throw new Exception('Journal ID required');
    }


    $id = intval($input['id']);
    $title = $input['title'] ?? '';
    $abstract = $input['abstract'] ?? '';
    $authors = $input['authors'] ?? [];
    $tags = $input['tags'] ?? [];         //  TAMBAH
    $pengurus = $input['pengurus'] ?? []; //  TAMBAH
    $email = $input['email'] ?? '';
    $contact = $input['contact'] ?? '';
    $volume = $input['volume'] ?? '';

    if (empty($title) || empty($abstract)) {
        throw new Exception('Title and abstract are required');
    }

    //  Encode to JSON
    $authorsJson = json_encode($authors);
    $tagsJson = json_encode($tags);
    $pengurusJson = json_encode($pengurus);

    $stmt = $pdo->prepare("
        UPDATE journals 
        SET title = ?, 
            abstract = ?, 
            authors = ?, 
            tags = ?,         --  TAMBAH
            pengurus = ?,     --  TAMBAH
            email = ?, 
            contact = ?, 
            volume = ?, 
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $title,
        $abstract,
        $authorsJson,
        $tagsJson,
        $pengurusJson,
        $email,
        $contact,
        $volume,
        $id
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'Journal updated successfully',
        'id' => $id
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
