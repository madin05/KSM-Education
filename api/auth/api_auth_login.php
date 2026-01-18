<?php
// api/auth/login.php

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../database/db.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Email and password required']);
    exit;
}

$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, password_hash, name, role FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    if (!password_verify($data['password'], $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];

    echo json_encode([
        'ok' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'role' => $user['role']
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error']);
    error_log("Login error: " . $e->getMessage());
}
