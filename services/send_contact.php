<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

function contact_client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

try {
    // Contact remains public, but preserve an authenticated sender id when a
    // valid JWT/session is present. Validation and abuse controls apply to all.
    $authUser = optional_auth();
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new InvalidArgumentException('Payload JSON tidak valid.');
    }

    // Honeypot: bot biasanya mengisi field tersembunyi ini.
    if (trim((string)($data['website'] ?? '')) !== '') {
        echo json_encode(['ok' => true, 'message' => 'Pesan berhasil dikirim.']);
        exit;
    }

    $name = trim((string)($data['name'] ?? ''));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $subject = trim((string)($data['subject'] ?? ''));
    $message = trim((string)($data['message'] ?? ''));

    if ($name === '' || mb_strlen($name) > 100) {
        throw new InvalidArgumentException('Nama wajib diisi dan maksimal 100 karakter.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        throw new InvalidArgumentException('Alamat email tidak valid.');
    }
    if ($subject === '' || mb_strlen($subject) > 150) {
        throw new InvalidArgumentException('Subjek wajib diisi dan maksimal 150 karakter.');
    }
    if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
        throw new InvalidArgumentException('Pesan harus berisi 10 sampai 5000 karakter.');
    }

    $ipHash = hash('sha256', contact_client_ip());
    $rateStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM contact_messages
         WHERE ip_hash = ? AND created_at >= (CURRENT_TIMESTAMP - INTERVAL 15 MINUTE)'
    );
    $rateStmt->execute([$ipHash]);
    if ((int)$rateStmt->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'message' => 'Terlalu banyak pesan. Coba lagi beberapa saat nanti.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO contact_messages (user_id, name, email, subject, message, ip_hash)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$authUser['id'] ?? null, $name, $email, $subject, $message, $ipHash]);

    http_response_code(201);
    echo json_encode(['ok' => true, 'message' => 'Pesan berhasil dikirim.']);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Send contact error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Pesan belum dapat dikirim. Coba lagi nanti.']);
}