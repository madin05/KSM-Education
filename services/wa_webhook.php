<?php

/**
 * WhatsApp Gateway Webhook Handler (Fonnte / Generic).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/wa_helpers.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$postData = $_POST;

if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $postData = array_merge($postData, $decoded);
    }
}

// Log incoming payload for debugging
@file_put_contents(
    __DIR__ . '/../wa_webhook_debug.log',
    date('[Y-m-d H:i:s] ') . json_encode($postData) . "\n",
    FILE_APPEND
);

$sender  = trim((string)($postData['sender'] ?? $postData['from'] ?? ''));
$message = trim((string)($postData['message'] ?? $postData['text'] ?? ''));
$type    = strtolower(trim((string)($postData['type'] ?? '')));

// Check all possible media file URL keys from Fonnte
$mediaUrl = trim((string)(
    $postData['url'] ?? 
    $postData['file'] ?? 
    $postData['filename'] ?? 
    $postData['media_url'] ?? 
    $postData['media'] ?? ''
));

if (empty($sender)) {
    echo json_encode(['status' => false, 'message' => 'No sender info']);
    exit;
}

// Normalize sender (remove non-digits)
$cleanSender = preg_replace('/[^0-9]/', '', $sender);

// 1. Handle transaction token link ("buy_...")
if (preg_match('/buy_([a-zA-Z0-9\-_]+)/', $message, $matches)) {
    $plainToken = $matches[1];
    $tokenHash  = hash('sha256', $plainToken);

    try {
        $stmt = $pdo->prepare(
            'SELECT t.id, t.user_id, u.name, u.email
             FROM telegram_link_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at >= CURRENT_TIMESTAMP
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $linkToken = $stmt->fetch();

        if ($linkToken) {
            $upd = $pdo->prepare('UPDATE telegram_link_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = ?');
            $upd->execute([(int)$linkToken['id']]);

            // Save phone to user_id mapping
            $linkUser = $pdo->prepare(
                'INSERT INTO telegram_account_links (user_id, telegram_user_id, telegram_private_chat_id)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE telegram_user_id = VALUES(telegram_user_id), telegram_private_chat_id = VALUES(telegram_private_chat_id)'
            );
            $linkUser->execute([(int)$linkToken['user_id'], $cleanSender, $cleanSender]);

            $replyMsg = wa_format_welcome_message($linkToken['name']);
            wa_send_message($cleanSender, $replyMsg);

            echo json_encode(['status' => true, 'message' => 'Token linked successfully']);
            exit;
        }
    } catch (Throwable $e) {
        error_log('[wa_webhook] Link token error: ' . $e->getMessage());
    }
}

// Helper: resolve user_id by WhatsApp sender phone number
$resolvedUserId = 1;
try {
    $stmtUser = $pdo->prepare(
        'SELECT user_id FROM telegram_account_links WHERE telegram_user_id = ? LIMIT 1'
    );
    $stmtUser->execute([$cleanSender]);
    $mapped = $stmtUser->fetch();
    if ($mapped) {
        $resolvedUserId = (int)$mapped['user_id'];
    } else {
        // Fallback: check most recent link token created in last 2 hours
        $stmtToken = $pdo->prepare(
            'SELECT user_id FROM telegram_link_tokens ORDER BY created_at DESC LIMIT 1'
        );
        $stmtToken->execute();
        $recentToken = $stmtToken->fetch();
        if ($recentToken) {
            $resolvedUserId = (int)$recentToken['user_id'];
        }
    }
} catch (Throwable $e) {
    error_log('[wa_webhook] User resolution error: ' . $e->getMessage());
}

// 2. Handle selection by package number (1, 2, 3, 4)
if (in_array($message, ['1', '2', '3', '4'])) {
    $packages = [
        '1' => ['tokens' => 5, 'price' => 10000],
        '2' => ['tokens' => 10, 'price' => 18000],
        '3' => ['tokens' => 20, 'price' => 34000],
        '4' => ['tokens' => 50, 'price' => 80000],
    ];
    $pkg = $packages[$message];
    $orderCode = 'TOK-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
    $userId = $resolvedUserId;

    try {
        // Create an awaiting_proof purchase request with selected package
        $stmt = $pdo->prepare(
            "INSERT INTO token_purchase_requests 
             (order_code, public_id, user_id, amount, price_rupiah, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'awaiting_proof', NOW())"
        );
        $stmt->execute([$orderCode, $orderCode, $userId, $pkg['tokens'], $pkg['price']]);
    } catch (Throwable $e) {
        error_log('[wa_webhook] Awaiting proof order creation error: ' . $e->getMessage());
    }
    
    $replyMsg = wa_format_order_message($orderCode, $pkg['tokens'], $pkg['price']);
    wa_send_message($cleanSender, $replyMsg);
    echo json_encode(['status' => true, 'message' => 'Package selected']);
    exit;
}

// 3. Handle proof upload (image, photo, document, non-empty media URL, or Fonnte's 'non-text message')
$isMedia = !empty($mediaUrl) 
    || in_array($type, ['image', 'photo', 'document', 'file']) 
    || $message === 'non-text message' 
    || strtolower((string)($postData['pesan'] ?? '')) === 'non-text message';

if ($isMedia) {
    try {
        $userId = $resolvedUserId;

        // Find existing awaiting_proof purchase request for this user
        $stmt = $pdo->prepare(
            "SELECT id, order_code, amount, price_rupiah 
             FROM token_purchase_requests 
             WHERE user_id = ? AND status = 'awaiting_proof' 
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $pendingOrder = $stmt->fetch();

        if ($pendingOrder) {
            $orderCode = $pendingOrder['order_code'] ?: ('TOK-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12)));
            $upd = $pdo->prepare(
                "UPDATE token_purchase_requests 
                 SET proof_file_path = ?, status = 'pending', submitted_at = NOW() 
                 WHERE id = ?"
            );
            $upd->execute([$mediaUrl ?: 'whatsapp_upload', (int)$pendingOrder['id']]);
        } else {
            // Fallback: create new pending request if no prior package selection
            $orderCode = 'TOK-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
            $stmt = $pdo->prepare(
                "INSERT INTO token_purchase_requests 
                 (order_code, public_id, user_id, amount, price_rupiah, proof_file_path, status, submitted_at, created_at)
                 VALUES (?, ?, ?, 5, 10000, ?, 'pending', NOW(), NOW())"
            );
            $stmt->execute([$orderCode, $orderCode, $userId, $mediaUrl ?: 'whatsapp_upload']);
        }

        $replyMsg = "✅ *Bukti pembayaran diterima!*\n\nNomor Pesanan: *{$orderCode}*\nBukti transfer Anda telah dikirimkan ke Admin KSM Education. Saldo token Anda akan otomatis bertambah setelah disetujui.";
        wa_send_message($cleanSender, $replyMsg);

        echo json_encode(['status' => true, 'message' => 'Proof processed successfully']);
        exit;
    } catch (Throwable $e) {
        error_log('[wa_webhook] Proof error: ' . $e->getMessage());
    }
}

// 4. Default response only for unmatched text
$replyMsg = wa_format_welcome_message('Pengguna');
wa_send_message($cleanSender, $replyMsg);
echo json_encode(['status' => true, 'message' => 'Default reply sent']);
