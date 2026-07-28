<?php

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

try {
    $data = json_decode((string)file_get_contents('php://input'), true);
    $token = trim((string)($data['token'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $confirmation = (string)($data['password_confirmation'] ?? '');

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new InvalidArgumentException('Token reset tidak valid.');
    }
    if (strlen($password) < 8 || strlen($password) > 128) {
        throw new InvalidArgumentException('Password baru harus terdiri dari 8 sampai 128 karakter.');
    }
    if ($password !== $confirmation) {
        throw new InvalidArgumentException('Konfirmasi password tidak cocok.');
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT prt.id, prt.user_id
         FROM password_reset_tokens prt
         JOIN users u ON u.id = prt.user_id
         WHERE prt.token_hash = ? AND prt.used_at IS NULL
           AND prt.expires_at > CURRENT_TIMESTAMP
           AND (u.account_status = \'active\' OR u.account_status IS NULL)
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([hash('sha256', $token)]);
    $reset = $stmt->fetch();
    if (!$reset) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Token reset tidak valid atau sudah kedaluwarsa.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('Password hashing failed.');
    }
    $update = $pdo->prepare(
        'UPDATE users SET password_hash = ?, password_changed_at = CURRENT_TIMESTAMP WHERE id = ?'
    );
    $update->execute([$hash, (int)$reset['user_id']]);
    $consume = $pdo->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE user_id = ? AND used_at IS NULL');
    $consume->execute([(int)$reset['user_id']]);
    $pdo->commit();

    echo json_encode(['ok' => true, 'message' => 'Password berhasil diperbarui. Silakan login kembali.']);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Reset password error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Password belum dapat diperbarui.']);
}