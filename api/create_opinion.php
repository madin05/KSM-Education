<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    error_log("=== CREATE OPINION API ===");
    error_log("Input: " . json_encode($input));
    
    // Extract fields
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    $category = $input['category'] ?? 'opini';
    $author_name = $input['authorname'] ?? $input['author_name'] ?? $input['authorName'] ?? '';
    
    // ✅ FIX: Use correct column name 'url'
    $file_upload_id = null;
    $cover_upload_id = null;
    
    if (isset($input['fileUrl'])) {
        $fileUrl = $input['fileUrl'];
        $stmt = $pdo->prepare("SELECT id FROM uploads WHERE url = ?");
        $stmt->execute([$fileUrl]);
        $fileUpload = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fileUpload) {
            $file_upload_id = $fileUpload['id'];
        }
        error_log("File URL: $fileUrl -> Upload ID: " . ($file_upload_id ?? 'not found'));
    }
    
    if (isset($input['coverUrl'])) {
        $coverUrl = $input['coverUrl'];
        $stmt = $pdo->prepare("SELECT id FROM uploads WHERE url = ?");
        $stmt->execute([$coverUrl]);
        $coverUpload = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($coverUpload) {
            $cover_upload_id = $coverUpload['id'];
        }
        error_log("Cover URL: $coverUrl -> Upload ID: " . ($cover_upload_id ?? 'not found'));
    }
    
    // Validate required fields
    if (!$title || !$author_name) {
        throw new Exception('Title and author name are required');
    }
    
    error_log("Inserting: title=$title, author=$author_name, file_id=$file_upload_id, cover_id=$cover_upload_id");
    
    // Insert into database
    $stmt = $pdo->prepare("
        INSERT INTO opinions (title, description, category, author_name, file_upload_id, cover_upload_id, views)
        VALUES (?, ?, ?, ?, ?, ?, 0)
    ");
    
    $stmt->execute([
        $title,
        $description,
        $category,
        $author_name,
        $file_upload_id,
        $cover_upload_id
    ]);
    
    $id = $pdo->lastInsertId();
    
    error_log("✅ Opinion created with ID: $id");
    
    echo json_encode([
        'ok' => true,
        'id' => (int)$id,
        'message' => 'Opinion created successfully'
    ]);
    
} catch (Exception $e) {
    error_log('❌ Create opinion error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?>
