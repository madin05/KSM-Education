<?php

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/jwt_middleware.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    http_response_code(405);
    header('Allow: PATCH');
    echo json_encode(['ok' => false, 'message' => 'Only PATCH allowed']);
    exit;
}

try {
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) {
        throw new InvalidArgumentException('Payload JSON tidak valid.');
    }

    $id = (int)($data['id'] ?? 0);
    $action = strtolower(trim((string)($data['action'] ?? '')));
    if ($id < 1 || !in_array($action, ['read', 'reply', 'close'], true)) {
        throw new InvalidArgumentException('ID atau aksi tidak valid.');
    }

    if ($action === 'read') {
        $stmt = $pdo->prepare(
            "UPDATE contact_messages SET status = IF(status = 'new', 'read', status),
                    read_at = COALESCE(read_at, CURRENT_TIMESTAMP) WHERE id = ?"
        );
        $stmt->execute([$id]);
    } elseif ($action === 'reply') {
        $reply = trim((string)($data['reply'] ?? ''));
        if ($reply === '' || mb_strlen($reply) > 5000) {
            throw new InvalidArgumentException('Balasan wajib diisi dan maksimal 5000 karakter.');
        }
        $stmt = $pdo->prepare(
            "UPDATE contact_messages SET status = 'replied', admin_reply = ?, replied_by = ?,
                    replied_at = CURRENT_TIMESTAMP, read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
             WHERE id = ?"
        );
        $stmt->execute([$reply, (int)$admin['id'], $id]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE contact_messages SET status = 'closed', closed_at = CURRENT_TIMESTAMP,
                    read_at = COALESCE(read_at, CURRENT_TIMESTAMP) WHERE id = ?"
        );
        $stmt->execute([$id]);
    }

    if ($stmt->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT 1 FROM contact_messages WHERE id = ?');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Pesan tidak ditemukan.']);
            exit;
        }
    }

    $result = $pdo->prepare(
        'SELECT id, name, email, subject, message, status, admin_reply,
                replied_at, read_at, closed_at, created_at, updated_at
         FROM contact_messages WHERE id = ?'
    );
    $result->execute([$id]);
    echo json_encode(['ok' => true, 'message' => 'Pesan diperbarui.', 'result' => $result->fetch()]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Contact message update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Gagal memperbarui pesan.']);
}