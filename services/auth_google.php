<?php
/**
 * Google Auth Handler
 * 
 * Verifies the Google ID Token sent by the client,
 * checks/registers the user in the database,
 * and issues app JWT and PHP Session.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_helper.php';

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || empty($data['credential'])) {
        echo json_encode(['ok' => false, 'message' => 'Token credential Google tidak ditemukan.']);
        exit;
    }

    $idToken = $data['credential'];

    // Verify token using Google OAuth2 TokenInfo API
    $verifyUrl = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($idToken);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verifyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        echo json_encode(['ok' => false, 'message' => 'Gagal memverifikasi token Google ke server Google.']);
        exit;
    }

    $payload = json_decode($response, true);

    if (!$payload || empty($payload['email'])) {
        echo json_encode(['ok' => false, 'message' => 'Token Google tidak valid atau email tidak ditemukan.']);
        exit;
    }

    // Verify Client ID if defined in .env
    $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?? '';
    if (!empty($clientId) && !empty($payload['aud']) && $payload['aud'] !== $clientId) {
        echo json_encode(['ok' => false, 'message' => 'Audience token tidak cocok dengan Client ID server.']);
        exit;
    }

    $email = trim($payload['email']);
    $name = trim($payload['name'] ?? '');

    // Check if user exists in our database
    $stmt = $pdo->prepare("SELECT id, name, role, email FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Automatically register the user if they don't exist
        $randomPassword = bin2hex(random_bytes(16));
        $passwordHash = password_hash($randomPassword, PASSWORD_DEFAULT);
        
        $stmtInsert = $pdo->prepare("INSERT INTO users (email, password_hash, name, role) VALUES (?, ?, ?, 'user')");
        $stmtInsert->execute([$email, $passwordHash, $name]);
        
        $newUserId = $pdo->lastInsertId();
        
        $user = [
            'id' => $newUserId,
            'name' => $name,
            'role' => 'user',
            'email' => $email
        ];
    }

    // Set PHP Session for backward compatibility
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];

    // Generate JWT Tokens
    $accessToken = generate_access_token($user);
    $refreshToken = generate_refresh_token($user);

    echo json_encode([
        'ok' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
            'email' => $user['email']
        ],
        'access_token' => $accessToken['token'],
        'refresh_token' => $refreshToken['token'],
        'expires_in' => $accessToken['expires_in']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'message' => 'Server Error: ' . $e->getMessage()
    ]);
}
