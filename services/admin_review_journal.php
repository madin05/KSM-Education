<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/submission_helpers.php';

$auth_user = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

try {
    $data = submission_json_body();
    $id = submission_positive_int($data['id'] ?? null, 'id');
    $action = strtolower(trim((string)($data['action'] ?? '')));
    if (!in_array($action, ['approve', 'reject'], true)) {
        throw new InvalidArgumentException('action harus approve atau reject.');
    }
    $reason = submission_optional_string($data, 'reason', 500);
    if ($action === 'reject' && $reason === null) {
        throw new InvalidArgumentException('Alasan penolakan wajib diisi.');
    }

    // Opini direview dengan alur yang sama seperti jurnal, hanya berbeda tabel.
    $type = strtolower(trim((string)($data['type'] ?? 'journal')));
    if (!in_array($type, ['journal', 'opinion'], true)) {
        throw new InvalidArgumentException('type harus journal atau opinion.');
    }
    $table = $type === 'opinion' ? 'opinions' : 'journals';
    $label = $type === 'opinion' ? 'Opini' : 'Jurnal';

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT status FROM {$table} WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) throw new DomainException("{$label} tidak ditemukan.");
    if ($row['status'] !== 'pending') {
        throw new DomainException("Hanya {$label} berstatus pending yang dapat direview.");
    }

    $status = $action === 'approve' ? 'published' : 'rejected';
    $stmt = $pdo->prepare(
        "UPDATE {$table}
         SET status = ?, rejection_reason = ?, reviewed_by = ?,
             reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'pending'"
    );
    $stmt->execute([$status, $action === 'reject' ? $reason : null, (int)$auth_user['id'], $id]);
    if ($stmt->rowCount() !== 1) throw new DomainException("Status {$label} telah berubah. Muat ulang data review.");
    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'id' => $id,
        'type' => $type,
        'status' => $status,
        'message' => "Review {$label} berhasil disimpan.",
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    submission_error($e);
}