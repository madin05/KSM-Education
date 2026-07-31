<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/token_service.php';

header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$secret = telegram_env('TELEGRAM_WEBHOOK_SECRET');
$received = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if ($secret === '' || strlen($secret) < 32 || !hash_equals($secret, $received)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$update = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($update) || !isset($update['update_id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT IGNORE INTO telegram_webhook_updates (update_id) VALUES (?)');
    $stmt->execute([(int)$update['update_id']]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['ok' => true, 'duplicate' => true]);
        exit;
    }

    if (isset($update['callback_query'])) {
        bot_callback($pdo, $update['callback_query']);
    } elseif (isset($update['message'])) {
        bot_message($pdo, $update['message']);
    }
} catch (Throwable $e) {
    telegram_log_exception('webhook:update', $e, ['update_id' => (int)$update['update_id']]);
    try {
        $stmt = $pdo->prepare('DELETE FROM telegram_webhook_updates WHERE update_id = ?');
        $stmt->execute([(int)$update['update_id']]);
    } catch (Throwable $cleanupError) {
        telegram_log_exception('webhook:update_cleanup', $cleanupError, ['update_id' => (int)$update['update_id']]);
    }

    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => true]);

function bot_message(PDO $pdo, array $message): void
{
    $chatId = (int)($message['chat']['id'] ?? 0);
    $chatType = (string)($message['chat']['type'] ?? '');
    $from = $message['from'] ?? [];
    $telegramUserId = (int)($from['id'] ?? 0);
    $text = trim((string)($message['text'] ?? ''));
    if ($chatId === 0 || $telegramUserId < 1) {
        return;
    }

    if ($chatType !== 'private') {
        bot_admin_group_message($pdo, $message, $text);
        return;
    }

    if (preg_match('/^\/start(?:@\w+)?(?:\s+buy_([A-Za-z0-9_-]{20,80}))?$/', $text, $match)) {
        try {
            $account = isset($match[1])
                ? telegram_consume_link_token($pdo, $match[1], $from, $chatId)
                : telegram_linked_account($pdo, $telegramUserId);
            if (!$account) {
                throw new RuntimeException('Akun belum terhubung. Tekan Beli Token dari website KSM Education terlebih dahulu.');
            }
            telegram_show_packages($chatId, (string)($account['name'] ?? ''));
        } catch (RuntimeException $e) {
            telegram_send_message($chatId, telegram_html($e->getMessage()));
        }
        return;
    }

    $account = telegram_linked_account($pdo, $telegramUserId);
    if (!$account) {
        telegram_send_message($chatId, 'Akun belum terhubung. Login di website KSM Education lalu tekan <b>Beli Token</b>.');
        return;
    }

    if (!empty($message['photo']) || !empty($message['document'])) {
        bot_submit_proof($pdo, $message, $account);
        return;
    }
    if (in_array(strtolower($text), ['/beli', 'beli token', 'menu'], true)) {
        telegram_show_packages($chatId, (string)$account['name']);
        return;
    }
    if (in_array(strtolower($text), ['/status', 'status'], true)) {
        bot_show_status($pdo, $chatId, (int)$account['user_id']);
        return;
    }

    telegram_send_message($chatId, 'Gunakan /beli untuk memilih paket atau /status untuk mengecek pesanan. Bukti transfer dapat berupa foto atau PDF.');
}

function bot_admin_group_message(PDO $pdo, array $message, string $text): void
{
    if (!preg_match('/^\/setup(?:@\w+)?\s+(\S+)$/', $text, $match)) {
        return;
    }
    $setupCode = telegram_env('TELEGRAM_ADMIN_SETUP_CODE');
    $chatId = (int)$message['chat']['id'];
    $userId = (int)($message['from']['id'] ?? 0);
    $currentAdminChatId = telegram_admin_chat_id($pdo);
    if ($currentAdminChatId && $currentAdminChatId !== $chatId) {
        telegram_send_message($chatId, 'Setup grup ditolak karena bot sudah terhubung ke grup admin lain.');
        return;
    }
    if ($setupCode === '' || strlen($setupCode) < 16 || !hash_equals($setupCode, $match[1]) || !telegram_is_chat_admin($chatId, $userId)) {
        telegram_send_message($chatId, 'Setup grup ditolak. Pastikan kode dan hak administrator benar.');
        return;
    }
    telegram_set_setting($pdo, 'admin_chat_id', (string)$chatId);
    telegram_send_message($chatId, '<b>KSMedu Admin berhasil dihubungkan.</b> Bukti pembayaran berikutnya akan dikirim ke grup ini. Hapus TELEGRAM_ADMIN_SETUP_CODE dari konfigurasi setelah setup.');
}

function bot_submit_proof(PDO $pdo, array $message, array $account): void
{
    $chatId = (int)$message['chat']['id'];
    $document = $message['document'] ?? null;
    if ($document && (string)($document['mime_type'] ?? '') !== 'application/pdf') {
        telegram_send_message($chatId, 'Dokumen bukti harus berformat PDF. Anda juga dapat mengirim foto bukti transfer.');
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT r.*, u.name, u.email, l.telegram_username
         FROM token_purchase_requests r
         JOIN users u ON u.id = r.user_id
         LEFT JOIN telegram_account_links l ON l.user_id = r.user_id
         WHERE r.user_id = ? AND r.telegram_user_id = ? AND r.status = 'awaiting_proof'
         ORDER BY r.id DESC LIMIT 1"
    );
    $stmt->execute([(int)$account['user_id'], (int)$account['telegram_user_id']]);
    $purchase = $stmt->fetch();
    if (!$purchase) {
        telegram_send_message($chatId, 'Belum ada pesanan yang menunggu bukti. Gunakan /beli dan pilih paket terlebih dahulu.');
        return;
    }

    $proofType = $document ? 'document' : 'photo';
    $photos = $message['photo'] ?? [];
    $proofFileId = $document ? (string)$document['file_id'] : (string)end($photos)['file_id'];
    $adminChatId = telegram_admin_chat_id($pdo);
    if (!$adminChatId) {
        telegram_send_message($chatId, 'Grup admin belum dikonfigurasi. Bukti belum disimpan; silakan coba lagi setelah menghubungi admin.');
        return;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE token_purchase_requests
             SET status = 'pending', telegram_proof_file_id = ?, telegram_proof_type = ?,
                 submitted_at = CURRENT_TIMESTAMP, admin_chat_id = ?
             WHERE id = ? AND status = 'awaiting_proof'"
        );
        $stmt->execute([$proofFileId, $proofType, $adminChatId, (int)$purchase['id']]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Pesanan sudah diproses oleh permintaan lain.');
        }

        $forward = telegram_api('forwardMessage', [
            'chat_id' => $adminChatId,
            'from_chat_id' => $chatId,
            'message_id' => (int)$message['message_id'],
        ]);
        $review = telegram_send_message(
            $adminChatId,
            telegram_review_text($purchase),
            ['reply_markup' => ['inline_keyboard' => [[
                ['text' => '✅ Approve', 'callback_data' => 'approve:' . (int)$purchase['id']],
                ['text' => '❌ Tolak', 'callback_data' => 'reject:' . (int)$purchase['id']],
            ]]]]
        );
        $stmt = $pdo->prepare(
            'UPDATE token_purchase_requests SET admin_forward_message_id = ?, admin_review_message_id = ? WHERE id = ?'
        );
        $stmt->execute([
            (int)$forward['result']['message_id'],
            (int)$review['result']['message_id'],
            (int)$purchase['id'],
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    telegram_send_message($chatId, '✅ Bukti pembayaran <code>' . telegram_html($purchase['public_id']) . '</code> sudah diteruskan ke admin untuk diverifikasi.');
}

function bot_callback(PDO $pdo, array $callback): void
{
    $callbackId = (string)($callback['id'] ?? '');
    $data = (string)($callback['data'] ?? '');
    try {
        if (preg_match('/^buy:(\d+)$/', $data, $match)) {
            bot_choose_package($pdo, $callback, (int)$match[1]);
        } elseif ($data === 'status') {
            $account = telegram_linked_account($pdo, (int)$callback['from']['id']);
            $chatId = (int)$callback['message']['chat']['id'];
            if (!$account || $chatId !== (int)$account['telegram_private_chat_id']) {
                throw new RuntimeException('Status hanya dapat dibuka dari chat pribadi yang terhubung.');
            }
            bot_show_status($pdo, $chatId, (int)$account['user_id']);
            telegram_api('answerCallbackQuery', ['callback_query_id' => $callbackId]);
        } elseif (preg_match('/^(approve|reject):(\d+)$/', $data, $match)) {
            bot_review($pdo, $callback, $match[1], (int)$match[2]);
        } else {
            telegram_api('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => 'Aksi tidak dikenali.']);
        }
    } catch (Throwable $e) {
        telegram_log_exception('callback:' . ($data !== '' ? $data : 'unknown'), $e, [
            'telegram_user_id' => (int)($callback['from']['id'] ?? 0),
            'chat_id' => (int)($callback['message']['chat']['id'] ?? 0),
        ]);
        telegram_api('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => substr($e->getMessage(), 0, 180),
            'show_alert' => true,
        ]);
    }

}

function bot_choose_package(PDO $pdo, array $callback, int $amount): void
{
    $packages = telegram_packages();
    if (!isset($packages[$amount])) {
        throw new RuntimeException('Paket token tidak valid.');
    }
    $telegramUserId = (int)$callback['from']['id'];
    $chatId = (int)$callback['message']['chat']['id'];
    $account = telegram_linked_account($pdo, $telegramUserId);
    if (!$account || $chatId !== (int)$account['telegram_private_chat_id']) {
        throw new RuntimeException('Akun Telegram belum terhubung dengan benar.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE token_purchase_requests SET status = 'cancelled'
             WHERE user_id = ? AND status = 'awaiting_proof'"
        );
        $stmt->execute([(int)$account['user_id']]);
        $publicId = token_generate_public_id();
        $stmt = $pdo->prepare(
            "INSERT INTO token_purchase_requests
               (public_id, user_id, amount, price_rupiah, status, telegram_chat_id, telegram_user_id)
             VALUES (?, ?, ?, ?, 'awaiting_proof', ?, ?)"
        );
        $stmt->execute([$publicId, (int)$account['user_id'], $amount, $packages[$amount], $chatId, $telegramUserId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    telegram_api('answerCallbackQuery', ['callback_query_id' => (string)$callback['id'], 'text' => 'Paket dipilih.']);
    $instructions = str_replace('\\n', "\n", telegram_require_config()['payment_instructions']);
    telegram_send_message(
        $chatId,
        "<b>Pesanan {$publicId}</b>\n\nPaket: <b>{$amount} token</b>\nTotal: <b>" . telegram_rupiah($packages[$amount])
        . "</b>\n\n" . telegram_html($instructions) . "\n\nKirim foto atau PDF bukti transfer ke chat ini."
    );
}

function bot_review(PDO $pdo, array $callback, string $action, int $requestId): void
{
    $chatId = (int)$callback['message']['chat']['id'];
    $adminId = (int)$callback['from']['id'];
    $adminChatId = telegram_admin_chat_id($pdo);
    if (!$adminChatId || $chatId !== $adminChatId || !telegram_is_chat_admin($chatId, $adminId)) {
        throw new RuntimeException('Hanya administrator grup KSMedu yang dapat memproses transaksi.');
    }

    $stmt = $pdo->prepare(
        'SELECT r.*, u.name, u.email, l.telegram_username
         FROM token_purchase_requests r JOIN users u ON u.id = r.user_id
         LEFT JOIN telegram_account_links l ON l.user_id = r.user_id
         WHERE r.id = ? LIMIT 1'
    );
    $stmt->execute([$requestId]);
    $purchase = $stmt->fetch();
    if (!$purchase) {
        throw new RuntimeException('Pesanan tidak ditemukan.');
    }

    // Approve/reject dijalankan langsung terhadap database pada proses PHP yang sama.
    // Tidak memakai HTTP loopback ke APP_URL agar tidak melewati Cloudflare/WAF
    // (request internal sebelumnya bisa dijawab HTML challenge sehingga JSON gagal di-parse).
    $result = $action === 'approve'
        ? token_approve_purchase($pdo, $requestId, $adminId)
        : token_reject_purchase($pdo, $requestId, $adminId);

    $label = $action === 'approve' ? '✅ DISETUJUI' : '❌ DITOLAK';
    telegram_api('editMessageText', [
        'chat_id' => $chatId,
        'message_id' => (int)$callback['message']['message_id'],
        'text' => telegram_review_text($purchase, $label) . "\n<b>Diproses oleh:</b> " . telegram_html((string)($callback['from']['first_name'] ?? $adminId)),
        'parse_mode' => 'HTML',
    ]);

    if (empty($result['already_processed'])) {
        $notice = $action === 'approve'
            ? '✅ Pembayaran <code>' . telegram_html($purchase['public_id']) . '</code> disetujui. <b>' . (int)$purchase['amount'] . ' token</b> telah masuk. Saldo: <b>' . (int)$result['balance'] . ' token</b>.'
            : '❌ Pembayaran <code>' . telegram_html($purchase['public_id']) . '</code> ditolak. Hubungi admin jika memerlukan klarifikasi.';
        telegram_send_message((int)$purchase['telegram_chat_id'], $notice);
    }
    telegram_api('answerCallbackQuery', [
        'callback_query_id' => (string)$callback['id'],
        'text' => !empty($result['already_processed']) ? 'Transaksi sudah diproses.' : 'Keputusan tersimpan.',
    ]);
}

function bot_show_status(PDO $pdo, int $chatId, int $userId): void
{
    $stmt = $pdo->prepare(
        'SELECT public_id, amount, price_rupiah, status, created_at
         FROM token_purchase_requests WHERE user_id = ? ORDER BY id DESC LIMIT 5'
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        telegram_send_message($chatId, 'Belum ada pesanan token. Gunakan /beli untuk memulai.');
        return;
    }
    $lines = ['<b>5 Pesanan Terakhir</b>'];
    foreach ($rows as $row) {
        $lines[] = '<code>' . telegram_html($row['public_id']) . '</code> — ' . (int)$row['amount']
            . ' token — ' . telegram_html((string)$row['status']);
    }
    telegram_send_message($chatId, implode("\n", $lines));
}
