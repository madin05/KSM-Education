<?php
// api/tracking/visitor.php

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../database/db.php';

// PUBLIC ACCESS - No auth required (analytics tracking)
// OPTIONAL AUTH - Uncomment kalau mau require login
// require_once __DIR__ . '/../auth/api_auth_middleware.php';
// $userId = requireAuth();

try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $pageUrl = $_GET['page'] ?? $_POST['page'] ?? '';

    // Check if visitors table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'visitors'");
    if ($checkTable->rowCount() == 0) {
        echo json_encode(['ok' => false, 'message' => 'Table visitors not found']);
        exit;
    }

    // Check if visitor already visited TODAY (unique per day)
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT id FROM visitors
        WHERE ip_address = ?
          AND DATE(visited_at) = ?
        LIMIT 1
    ");
    $stmt->execute([$ip, $today]);

    if ($stmt->rowCount() == 0) {
        // New visitor for today, insert
        $stmtInsert = $pdo->prepare("
            INSERT INTO visitors (ip_address, user_agent, page_url)
            VALUES (?, ?, ?)
        ");
        $stmtInsert->execute([$ip, $userAgent, $pageUrl]);

        echo json_encode([
            'ok' => true,
            'message' => 'Visitor tracked',
            'new' => true,
            'ip' => $ip
        ]);
    } else {
        echo json_encode([
            'ok' => true,
            'message' => 'Already tracked today',
            'new' => false,
            'ip' => $ip
        ]);
    }

} catch (PDOException $e) {
    error_log('Track visitor error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database error'
    ]);
} catch (Exception $e) {
    error_log('Track visitor error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
?>