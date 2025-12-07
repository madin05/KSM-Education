<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$id) {
        throw new Exception('Opinion ID is required');
    }
    
    error_log("=== GET OPINION API ===");
    error_log("Opinion ID: $id");
    
    // ✅ FIX: JOIN with uploads table using 'url' column
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            uf.url AS file_url,
            uc.url AS cover_url
        FROM opinions o
        LEFT JOIN uploads uf ON o.file_upload_id = uf.id
        LEFT JOIN uploads uc ON o.cover_upload_id = uc.id
        WHERE o.id = ?
    ");
    
    $stmt->execute([$id]);
    
    $opinion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$opinion) {
        error_log("Opinion not found with ID: $id");
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'message' => 'Opinion not found',
            'id' => $id
        ]);
        exit;
    }
    
    // Increment view count
    try {
        $updateStmt = $pdo->prepare("UPDATE opinions SET views = views + 1 WHERE id = ?");
        $updateStmt->execute([$id]);
        $opinion['views'] = ($opinion['views'] ?? 0) + 1;
    } catch (Exception $e) {
        error_log("Warning: Could not increment views: " . $e->getMessage());
    }
    
    error_log("Opinion found: " . $opinion['title']);
    error_log("File URL: " . ($opinion['file_url'] ?? 'null'));
    error_log("Cover URL: " . ($opinion['cover_url'] ?? 'null'));
    
    echo json_encode([
        'ok' => true,
        'result' => $opinion,
        'message' => 'Opinion retrieved successfully'
    ]);
    
} catch (Exception $e) {
    error_log('❌ Get opinion error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?>
