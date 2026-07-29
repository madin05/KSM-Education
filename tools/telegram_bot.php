<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../services/db.php';
require_once __DIR__ . '/../services/telegram_helpers.php';

$command = $argv[1] ?? 'check';

try {
    switch ($command) {
        case 'check':
            telegram_cli_check($pdo);
            break;
        case 'smoke-local':
            telegram_cli_smoke_local($pdo);
            break;
        case 'smoke-webhook':
            telegram_cli_smoke_webhook($pdo);
            break;
        case 'set-webhook':
            telegram_cli_set_webhook($argv[2] ?? '');
            break;
        case 'delete-webhook':
            telegram_api('deleteWebhook', ['drop_pending_updates' => false]);
            echo "[OK] Webhook Telegram dihapus.\n";
            break;
        default:
            telegram_cli_usage();
            exit(2);
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

function telegram_cli_check(PDO $pdo): void
{
    $requiredSecrets = [
        'TELEGRAM_BOT_TOKEN' => 20,
        'TELEGRAM_WEBHOOK_SECRET' => 32,
        'TELEGRAM_INTERNAL_SECRET' => 32,
    ];
    foreach ($requiredSecrets as $name => $minimumLength) {
        $value = telegram_env($name);
        telegram_cli_result(strlen($value) >= $minimumLength, $name . ' terkonfigurasi');
    }

    $tables = [
        'telegram_account_links',
        'telegram_link_tokens',
        'telegram_bot_settings',
        'telegram_webhook_updates',
        'token_purchase_requests',
        'user_token_wallets',
        'token_transactions',
    ];
    $placeholders = implode(',', array_fill(0, count($tables), '?'));
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$placeholders})"
    );
    $stmt->execute($tables);
    telegram_cli_result((int)$stmt->fetchColumn() === count($tables), 'Tabel alur pembelian token lengkap');

    $purchaseColumns = [
        'public_id',
        'user_id',
        'amount',
        'price_rupiah',
        'status',
        'telegram_chat_id',
        'telegram_user_id',
        'telegram_proof_file_id',
        'telegram_proof_type',
        'admin_chat_id',
        'admin_forward_message_id',
        'admin_review_message_id',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'processed_by_telegram_id',
        'created_at',
    ];
    $placeholders = implode(',', array_fill(0, count($purchaseColumns), '?'));
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests'
           AND COLUMN_NAME IN ({$placeholders})"
    );
    $stmt->execute($purchaseColumns);
    telegram_cli_result(
        (int)$stmt->fetchColumn() === count($purchaseColumns),
        'Kolom lifecycle pembelian token lengkap'
    );

    $ledgerColumns = ['description', 'processed_by_telegram_id'];
    $placeholders = implode(',', array_fill(0, count($ledgerColumns), '?'));
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_transactions'
           AND COLUMN_NAME IN ({$placeholders})"
    );
    $stmt->execute($ledgerColumns);
    telegram_cli_result(
        (int)$stmt->fetchColumn() === count($ledgerColumns),
        'Kolom audit transaksi Telegram lengkap'
    );

    $stmt = $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests'
           AND COLUMN_NAME = 'status' LIMIT 1"
    );
    $statusType = strtolower((string)$stmt->fetchColumn());
    $requiredStatuses = ['awaiting_proof', 'pending', 'approved', 'rejected', 'cancelled'];
    $hasAllStatuses = true;
    foreach ($requiredStatuses as $status) {
        if (strpos($statusType, "'{$status}'") === false) {
            $hasAllStatuses = false;
            break;
        }
    }
    telegram_cli_result($hasAllStatuses, 'Status lifecycle pembelian token valid');

    $me = telegram_api('getMe');
    $username = (string)($me['result']['username'] ?? 'unknown');
    telegram_cli_result(true, 'Bot API aktif sebagai @' . $username);

    $info = telegram_api('getWebhookInfo')['result'] ?? [];
    $url = (string)($info['url'] ?? '');
    if ($url === '') {
        echo "[WARN] Webhook belum didaftarkan.\n";
    } else {
        echo '[OK] Webhook: ' . $url . PHP_EOL;
    }
    echo '[INFO] Pending update: ' . (int)($info['pending_update_count'] ?? 0) . PHP_EOL;
    if (!empty($info['last_error_message'])) {
        echo '[WARN] Error Telegram terakhir: ' . $info['last_error_message'] . PHP_EOL;
    }

    $adminChatId = telegram_admin_chat_id($pdo);
    if (!$adminChatId) {
        echo "[WARN] Grup admin belum terhubung; approval pembelian belum dapat dilakukan.\n";
    } else {
        $chat = telegram_api('getChat', ['chat_id' => $adminChatId]);
        $title = (string)($chat['result']['title'] ?? $adminChatId);
        telegram_cli_result(true, 'Grup approval aktif: ' . $title);
    }
}

function telegram_cli_smoke_local(PDO $pdo): void
{
    $appUrl = rtrim(telegram_env('APP_URL'), '/');
    $secret = telegram_env('TELEGRAM_WEBHOOK_SECRET');
    if ($appUrl === '' || strlen($secret) < 32) {
        throw new RuntimeException('APP_URL atau TELEGRAM_WEBHOOK_SECRET belum valid.');
    }

    telegram_cli_smoke_endpoint($pdo, $appUrl . '/services/telegram_webhook.php');
}

function telegram_cli_smoke_webhook(PDO $pdo): void
{
    $info = telegram_api('getWebhookInfo')['result'] ?? [];
    $endpoint = (string)($info['url'] ?? '');
    if ($endpoint === '' || stripos($endpoint, 'https://') !== 0) {
        throw new RuntimeException('Webhook HTTPS belum terdaftar.');
    }
    telegram_cli_smoke_endpoint($pdo, $endpoint);
}

function telegram_cli_smoke_endpoint(PDO $pdo, string $endpoint): void
{
    $secret = telegram_env('TELEGRAM_WEBHOOK_SECRET');
    if (strlen($secret) < 32) {
        throw new RuntimeException('TELEGRAM_WEBHOOK_SECRET belum valid.');
    }

    $updateId = random_int(1000000000, 2000000000);
    $body = json_encode(['update_id' => $updateId], JSON_UNESCAPED_SLASHES);

    [$badStatus] = telegram_cli_post($endpoint, $body, 'invalid-secret');
    telegram_cli_result($badStatus === 403, 'Webhook menolak secret yang salah (HTTP 403)');

    [$firstStatus, $firstBody] = telegram_cli_post($endpoint, $body, $secret);
    $first = json_decode($firstBody, true);
    telegram_cli_result($firstStatus === 200 && !empty($first['ok']), 'Webhook menerima update valid');

    [$secondStatus, $secondBody] = telegram_cli_post($endpoint, $body, $secret);
    $second = json_decode($secondBody, true);
    telegram_cli_result(
        $secondStatus === 200 && !empty($second['duplicate']),
        'Webhook menangani update duplikat secara idempotent'
    );

    $stmt = $pdo->prepare('DELETE FROM telegram_webhook_updates WHERE update_id = ?');
    $stmt->execute([$updateId]);
    echo "[OK] Data smoke test dibersihkan.\n";
}

function telegram_cli_set_webhook(string $publicBaseUrl): void
{
    $publicBaseUrl = rtrim($publicBaseUrl, '/');
    if (!filter_var($publicBaseUrl, FILTER_VALIDATE_URL) || stripos($publicBaseUrl, 'https://') !== 0) {
        throw new InvalidArgumentException('Gunakan base URL HTTPS publik, contoh https://abc.trycloudflare.com');
    }

    $secret = telegram_env('TELEGRAM_WEBHOOK_SECRET');
    if (strlen($secret) < 32) {
        throw new RuntimeException('TELEGRAM_WEBHOOK_SECRET minimal 32 karakter.');
    }

    $url = $publicBaseUrl . '/services/telegram_webhook.php';
    telegram_api('setWebhook', [
        'url' => $url,
        'secret_token' => $secret,
        'allowed_updates' => ['message', 'callback_query'],
        'drop_pending_updates' => false,
    ]);
    echo '[OK] Webhook terdaftar: ' . $url . PHP_EOL;
}

function telegram_cli_post(string $url, string $body, string $secret): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Tidak dapat memulai HTTP smoke test.');
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Telegram-Bot-Api-Secret-Token: ' . $secret,
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($response === false) {
        throw new RuntimeException('Endpoint lokal tidak dapat diakses: ' . $error);
    }
    return [$status, (string)$response];
}

function telegram_cli_result(bool $success, string $message): void
{
    echo ($success ? '[OK] ' : '[FAIL] ') . $message . PHP_EOL;
    if (!$success) {
        throw new RuntimeException($message);
    }
}

function telegram_cli_usage(): void
{
    echo "KSMedu Telegram utility\n\n";
    echo "  php tools/telegram_bot.php check\n";
    echo "  php tools/telegram_bot.php smoke-local\n";
    echo "  php tools/telegram_bot.php smoke-webhook\n";
    echo "  php tools/telegram_bot.php set-webhook https://PUBLIC-URL\n";
    echo "  php tools/telegram_bot.php delete-webhook\n";
}