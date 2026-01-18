<?php
// api/auth/me.php

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../database/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, email, name, role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'User not found']);
        exit;
    }

    echo json_encode(['ok' => true, 'user' => $user]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error']);
    error_log("Auth check error: " . $e->getMessage());
}
