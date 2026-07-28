<?php

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

function reset_base_url(): string
{
    $configured = trim((string)get_env_var('APP_URL', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . APP_ROOT;
}

try {
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new InvalidArgumentException('Request body must be valid JSON.');
    }
    $email = strtolower(trim((string)($data['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        throw new InvalidArgumentException('Alamat email tidak valid.');
    }

    $genericMessage = 'Jika email terdaftar, tautan reset password akan dikirim.';
    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $rateStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM password_reset_tokens
         WHERE requested_ip_hash = ? AND created_at >= (CURRENT_TIMESTAMP - INTERVAL 15 MINUTE)'
    );
    $rateStmt->execute([$ipHash]);
    if ((int)$rateStmt->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'message' => 'Terlalu banyak permintaan. Coba lagi beberapa saat nanti.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM users WHERE email = ? AND (account_status = 'active' OR account_status IS NULL) LIMIT 1"
    );
    $stmt->execute([$email]);
    $userId = (int)($stmt->fetchColumn() ?: 0);

    if ($userId > 0) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $pdo->beginTransaction();
        $invalidate = $pdo->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = ? AND used_at IS NULL');
        $invalidate->execute([$userId]);
        $insert = $pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, requested_ip_hash)
             VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 MINUTE), ?)'
        );
        $insert->execute([$userId, $tokenHash, $ipHash]);
        $pdo->commit();

        $resetUrl = reset_base_url() . '/user/reset_password.php?token=' . rawurlencode($token);
        $subject = 'Reset Password KSM Education';
        $body = "Gunakan tautan berikut untuk mengatur password baru. Tautan berlaku 30 menit:\n\n{$resetUrl}\n\nJika Anda tidak meminta reset, abaikan email ini.";
        $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
        $from = trim((string)get_env_var('MAIL_FROM', ''));
        if ($from !== '') {
            $headers .= 'From: ' . $from . "\r\n";
        }

        if (!@mail($email, $subject, $body, $headers)) {
            error_log('Password reset mail could not be sent for user id ' . $userId . '. Configure PHP mail transport.');
        }
    }

    echo json_encode(['ok' => true, 'message' => $genericMessage]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Forgot password error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Permintaan belum dapat diproses.']);
}