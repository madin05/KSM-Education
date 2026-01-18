<?php
// api/uploads/create.php

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../auth/api_auth_middleware.php';

// AUTH REQUIRED - Admin only
$userId = requireAuth();

try {
    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'file not provided']);
        exit;
    }

    $file = $_FILES['file'];
    $maxSize = 20 * 1024 * 1024; // 20MB limit

    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'File too large (max 20MB)']);
        exit;
    }

    // Upload directory
    $uploadDir = __DIR__ . '/../../../uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Sanitize filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeName = bin2hex(random_bytes(12)) . ($ext ? '.' . $ext : '');
    $target = $uploadDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Cannot move uploaded file']);
        exit;
    }

    // Public URL path
    $publicUrl = '/uploads/' . $safeName;
    $mime = $file['type'] ?? mime_content_type($target);
    $size = (int)$file['size'];

    // Insert to database
    $stmt = $pdo->prepare("INSERT INTO uploads (filename, original_name, mime, size, url) VALUES (?,?,?,?,?)");
    $stmt->execute([$safeName, $file['name'], $mime, $size, $publicUrl]);
    $uploadId = $pdo->lastInsertId();

    error_log("File uploaded: $publicUrl (ID: $uploadId)");

    echo json_encode([
        'ok' => true,
        'id' => $uploadId,
        'url' => $publicUrl,
        'filename' => $safeName
    ]);

} catch (PDOException $e) {
    error_log('Upload error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log('Upload error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
?>