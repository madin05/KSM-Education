<?php
// services/admin/token_request_action.php
// Verifikasi manual top-up token oleh admin dari panel web.
// Method: POST { "id": <request_id>, "action": "approve"|"reject", "reason": "..." }
//
// Approve mengkredit saldo user + mencatat ledger token_transactions
// (idempoten: request yang sudah approved tidak dikredit dua kali).
// Jika user terhubung Telegram, hasil keputusan dikirim sebagai notifikasi.

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/jwt_middleware.php';
require_once dirname(__DIR__) . '/token_service.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

try {
    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $requestId = (int)($payload['id'] ?? 0);
    $action = strtolower(trim((string)($payload['action'] ?? '')));
    $reason = trim((string)($payload['reason'] ?? ''));

    if ($requestId < 1) {
        throw new InvalidArgumentException('ID permintaan token tidak valid.');
    }
    if (!in_array($action, ['approve', 'reject'], true)) {
        throw new InvalidArgumentException('Aksi harus approve atau reject.');
    }

    $adminId = (int)($admin['id'] ?? $admin['user_id'] ?? 0);

    $result = $action === 'approve'
        ? token_admin_approve_purchase($pdo, $requestId, $adminId)
        : token_admin_reject_purchase($pdo, $requestId, $adminId, $reason);

    // Notifikasi Telegram bersifat best-effort: kegagalan kirim tidak boleh
    // membatalkan keputusan yang sudah tersimpan di database.
    try {
        $stmt = $pdo->prepare(
            'SELECT public_id, amount, telegram_chat_id
             FROM token_purchase_requests WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        $chatId = (int)($request['telegram_chat_id'] ?? 0);

        if ($chatId > 0 && !($result['already_processed'] ?? false)) {
            require_once dirname(__DIR__) . '/telegram_helpers.php';
            $text = $action === 'approve'
                ? "✅ Top-up {$request['public_id']} disetujui admin.\n"
                    . "Token masuk: {$request['amount']}\n"
                    . 'Saldo sekarang: ' . (int)($result['balance'] ?? 0)
                : "❌ Top-up {$request['public_id']} ditolak admin."
                    . ($reason !== '' ? "\nAlasan: {$reason}" : '');
            telegram_send_message($chatId, $text);
        }
    } catch (Throwable $notifyError) {
        error_log('Token decision notify failed: ' . $notifyError->getMessage());
    }

    echo json_encode([
        'ok' => true,
        'action' => $action,
        'alreadyProcessed' => (bool)($result['already_processed'] ?? false),
        'publicId' => $result['public_id'] ?? null,
        'balance' => isset($result['balance']) ? (int)$result['balance'] : null,
        'message' => $action === 'approve'
            ? 'Top-up disetujui dan saldo token sudah ditambahkan.'
            : 'Permintaan top-up ditolak.',
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (RuntimeException $e) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Admin token action error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Gagal memproses permintaan token.']);
}
