<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/submission_helpers.php';

$auth_user = require_auth();

if (!in_array($_SERVER['REQUEST_METHOD'], ['PATCH', 'POST'], true)) {
    http_response_code(405);
    header('Allow: PATCH, POST');
    echo json_encode(['ok' => false, 'message' => 'Only PATCH or POST allowed']);
    exit;
}

try {
    $data = submission_json_body();
    $id = submission_positive_int($data['id'] ?? null, 'id');
    $userId = (int)$auth_user['id'];
    $allowedFields = ['title', 'abstract', 'volume', 'authors', 'tags', 'pengurus', 'email', 'contact', 'file_upload_id', 'cover_upload_id'];
    $provided = array_intersect($allowedFields, array_keys($data));
    if (!$provided) throw new InvalidArgumentException('Tidak ada perubahan yang dikirim.');

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT status FROM journals WHERE id = ? AND user_id = ? FOR UPDATE');
    $stmt->execute([$id, $userId]);
    $journal = $stmt->fetch();
    if (!$journal) throw new DomainException('Jurnal tidak ditemukan atau bukan milik Anda.');
    if (!in_array($journal['status'], ['draft', 'pending', 'rejected'], true)) {
        throw new DomainException('Jurnal yang sudah terbit tidak dapat diedit oleh user.');
    }

    $updates = [];
    $params = [];
    foreach ($provided as $field) {
        if ($field === 'title') $value = submission_required_string($data, $field, 512);
        elseif ($field === 'abstract') $value = submission_required_string($data, $field, 20000);
        elseif ($field === 'volume') $value = submission_required_string($data, $field, 100);
        elseif ($field === 'email') {
            $value = submission_required_string($data, $field, 255);
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Format email tidak valid.');
        } elseif ($field === 'contact') $value = submission_required_string($data, $field, 100);
        elseif (in_array($field, ['authors', 'tags', 'pengurus'], true)) {
            $value = submission_json_list($data, $field, $field === 'authors');
        } elseif ($field === 'file_upload_id') {
            $value = submission_positive_int($data[$field], $field);
            submission_assert_upload($pdo, $value, $userId, 'PDF');
        } else {
            $value = null;
            if ($data[$field] !== null && $data[$field] !== '') {
                $value = submission_positive_int($data[$field], $field);
                submission_assert_upload($pdo, $value, $userId, 'cover');
            }
        }
        $updates[] = "{$field} = ?";
        $params[] = $value;
    }

    // A corrected rejected submission returns to the moderation queue.
    $updates[] = "status = CASE WHEN status = 'rejected' THEN 'pending' ELSE status END";
    $updates[] = 'rejection_reason = NULL';
    $updates[] = 'reviewed_by = NULL';
    $updates[] = 'reviewed_at = NULL';
    $updates[] = 'updated_at = CURRENT_TIMESTAMP';
    $params[] = $id;
    $params[] = $userId;

    $stmt = $pdo->prepare('UPDATE journals SET ' . implode(', ', $updates) . ' WHERE id = ? AND user_id = ?');
    $stmt->execute($params);
    $pdo->commit();

    echo json_encode(['ok' => true, 'id' => $id, 'message' => 'Jurnal berhasil diperbarui.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    submission_error($e);
}