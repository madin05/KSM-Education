<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/submission_helpers.php';

$auth_user = require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    header('Allow: DELETE');
    echo json_encode(['ok' => false, 'message' => 'Only DELETE allowed']);
    exit;
}

try {
    $data = submission_json_body();
    $id = submission_positive_int($data['id'] ?? ($_GET['id'] ?? null), 'id');
    $userId = (int)$auth_user['id'];

    $stmt = $pdo->prepare(
        "DELETE FROM journals
         WHERE id = ? AND user_id = ? AND status IN ('draft', 'pending', 'rejected')"
    );
    $stmt->execute([$id, $userId]);
    if ($stmt->rowCount() !== 1) {
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'message' => 'Jurnal tidak ditemukan, bukan milik Anda, atau sudah terbit.',
            'code' => 'NOT_DELETABLE',
        ]);
        exit;
    }

    echo json_encode(['ok' => true, 'id' => $id, 'message' => 'Jurnal berhasil dihapus.']);
} catch (Throwable $e) {
    submission_error($e);
}