<?php
// api/journals/edit.php

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
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
        echo json_encode(['ok' => false, 'message' => 'ID required']);
        exit;
    }

    $id = (int)$data['id'];
    $title = trim($data['title'] ?? '');
    $abstract = $data['abstract'] ?? null;
    $email = $data['email'] ?? null;
    $contact = $data['contact'] ?? null;
    $volume = $data['volume'] ?? null;

    // Validate required fields
    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Title is required']);
        exit;
    }

    // JSON encode arrays
    $authorsJson = isset($data['authors']) && is_array($data['authors'])
        ? json_encode($data['authors'])
        : null;
    $tagsJson = isset($data['tags']) && is_array($data['tags'])
        ? json_encode($data['tags'])
        : null;
    $pengurusJson = isset($data['pengurus']) && is_array($data['pengurus'])
        ? json_encode($data['pengurus'])
        : null;

    // Check if journal exists
    $checkStmt = $pdo->prepare("SELECT id FROM journals WHERE id = ?");
    $checkStmt->execute([$id]);

    if ($checkStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Journal not found']);
        exit;
    }

    // Update journal
    $stmt = $pdo->prepare("
        UPDATE journals
        SET title = ?,
            abstract = ?,
            authors = ?,
            tags = ?,
            pengurus = ?,
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
} catch (PDOException $e) {
    error_log('Edit journal error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database error'
    ]);
} catch (Exception $e) {
    error_log('Edit journal error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
?>