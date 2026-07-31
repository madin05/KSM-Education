<?php

/** Shared helpers for the KSMedu Telegram bot. Sends no HTTP response. */

function telegram_env(string $name, string $default = ''): string
{
    return trim((string)get_env_var($name, $default));
}

function telegram_require_config(): array
{
    $config = [
        'token' => telegram_env('TELEGRAM_BOT_TOKEN'),
        'username' => ltrim(telegram_env('TELEGRAM_BOT_USERNAME', 'KSMedu_bot'), '@'),
        'webhook_secret' => telegram_env('TELEGRAM_WEBHOOK_SECRET'),
        'internal_secret' => telegram_env('TELEGRAM_INTERNAL_SECRET'),
        'setup_code' => telegram_env('TELEGRAM_ADMIN_SETUP_CODE'),
        'payment_instructions' => telegram_env(
            'TELEGRAM_PAYMENT_INSTRUCTIONS',
            'Silakan transfer sesuai nominal paket ke rekening/QRIS KSM Education, lalu kirim foto atau PDF bukti transfer di chat ini.'
        ),
    ];

    if ($config['token'] === '') {
        throw new RuntimeException('TELEGRAM_BOT_TOKEN belum dikonfigurasi.');
    }

    return $config;
}

function telegram_packages(): array
{
    $raw = telegram_env('TELEGRAM_TOKEN_PACKAGES', '5:10000,10:18000,20:34000,50:80000');
    $packages = [];
    foreach (explode(',', $raw) as $item) {
        $parts = array_map('trim', explode(':', $item, 2));
        if (count($parts) !== 2) {
            continue;
        }
        $amount = (int)$parts[0];
        $price = (int)$parts[1];
        if ($amount > 0 && $amount <= 10000 && $price > 0) {
            $packages[$amount] = $price;
        }
    }
    if (!$packages) {
        throw new RuntimeException('TELEGRAM_TOKEN_PACKAGES tidak valid.');
    }
    ksort($packages);
    return $packages;
}

function telegram_rupiah(int $amount): string
{
    return 'Rp' . number_format($amount, 0, ',', '.');
}

function telegram_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function telegram_api(string $method, array $payload = []): array
{
    $config = telegram_require_config();
    $url = 'https://api.telegram.org/bot' . $config['token'] . '/' . $method;
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Tidak dapat memulai koneksi Telegram.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $error !== '') {
        throw new RuntimeException('Koneksi Telegram gagal: ' . $error);
    }
    $decoded = json_decode((string)$body, true);
    if ($status < 200 || $status >= 300 || !is_array($decoded) || empty($decoded['ok'])) {
        $description = is_array($decoded) ? (string)($decoded['description'] ?? 'Unknown Telegram error') : 'Invalid Telegram response';
        throw new RuntimeException('Telegram API gagal: ' . $description);
    }
    return $decoded;
}

function telegram_send_message(int $chatId, string $text, array $extra = []): array
{
    return telegram_api('sendMessage', $extra + [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ]);
}

function telegram_get_setting(PDO $pdo, string $key): ?string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM telegram_bot_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (string)$value;
}

function telegram_set_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO telegram_bot_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function telegram_admin_chat_id(PDO $pdo): ?int
{
    $envChatId = telegram_env('TELEGRAM_ADMIN_CHAT_ID');
    if ($envChatId !== '' && preg_match('/^-?\d+$/', $envChatId)) {
        return (int)$envChatId;
    }
    $stored = telegram_get_setting($pdo, 'admin_chat_id');
    return $stored !== null && preg_match('/^-?\d+$/', $stored) ? (int)$stored : null;
}

function telegram_is_chat_admin(int $chatId, int $telegramUserId): bool
{
    $response = telegram_api('getChatMember', [
        'chat_id' => $chatId,
        'user_id' => $telegramUserId,
    ]);
    $status = (string)($response['result']['status'] ?? '');
    return in_array($status, ['creator', 'administrator'], true);
}

function telegram_package_keyboard(): array
{
    $rows = [];
    foreach (telegram_packages() as $amount => $price) {
        $rows[] = [[
            'text' => sprintf('⚡ %d Token — %s', $amount, telegram_rupiah($price)),
            'callback_data' => 'buy:' . $amount,
        ]];
    }
    $rows[] = [[
        'text' => '📋 Cek Status Pesanan',
        'callback_data' => 'status',
    ]];
    return ['inline_keyboard' => $rows];
}

function telegram_show_packages(int $chatId, string $name = ''): void
{
    $greeting = $name !== '' ? 'Halo, <b>' . telegram_html($name) . "</b>!\n\n" : '';
    telegram_send_message(
        $chatId,
        $greeting . "Selamat datang di <b>KSM Education Token Shop</b>.\n\n"
        . "Pilih paket token di bawah. Setelah memilih paket, transfer sesuai nominal dan kirim bukti pembayarannya di chat ini.\n\n"
        . "🔒 Saldo hanya ditambahkan setelah admin KSMedu menyetujui bukti transfer.",
        ['reply_markup' => telegram_package_keyboard()]
    );
}

function telegram_linked_account(PDO $pdo, int $telegramUserId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT l.user_id, l.telegram_user_id, l.telegram_private_chat_id, u.name, u.email
         FROM telegram_account_links l
         JOIN users u ON u.id = l.user_id
         WHERE l.telegram_user_id = ? LIMIT 1'
    );
    $stmt->execute([$telegramUserId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function telegram_consume_link_token(PDO $pdo, string $plainToken, array $from, int $chatId): array
{
    $hash = hash('sha256', $plainToken);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT t.id, t.user_id, u.name, u.email
             FROM telegram_link_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at >= CURRENT_TIMESTAMP
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$hash]);
        $link = $stmt->fetch();
        if (!$link) {
            throw new RuntimeException('Tautan pembelian tidak valid atau sudah kedaluwarsa. Buka kembali tombol Beli Token di website.');
        }

        $telegramUserId = (int)($from['id'] ?? 0);
        if ($telegramUserId < 1) {
            throw new RuntimeException('Akun Telegram tidak valid.');
        }

        $stmt = $pdo->prepare('DELETE FROM telegram_account_links WHERE user_id = ? OR telegram_user_id = ?');
        $stmt->execute([(int)$link['user_id'], $telegramUserId]);

        $displayName = trim((string)($from['first_name'] ?? '') . ' ' . (string)($from['last_name'] ?? ''));
        $stmt = $pdo->prepare(
            'INSERT INTO telegram_account_links
               (user_id, telegram_user_id, telegram_private_chat_id, telegram_username, telegram_display_name)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$link['user_id'],
            $telegramUserId,
            $chatId,
            isset($from['username']) ? substr((string)$from['username'], 0, 64) : null,
            substr($displayName, 0, 255),
        ]);

        $stmt = $pdo->prepare('UPDATE telegram_link_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([(int)$link['id']]);
        $pdo->commit();
        return $link;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function telegram_review_text(array $purchase, string $statusLabel = '⏳ MENUNGGU REVIEW'): string
{
    $username = !empty($purchase['telegram_username']) ? '@' . $purchase['telegram_username'] : '-';
    return "<b>🧾 PERMINTAAN PEMBELIAN TOKEN</b>\n\n"
        . '<b>ID:</b> <code>' . telegram_html((string)$purchase['public_id']) . "</code>\n"
        . '<b>User web:</b> ' . telegram_html((string)$purchase['name']) . "\n"
        . '<b>Email:</b> ' . telegram_html((string)$purchase['email']) . "\n"
        . '<b>Telegram:</b> ' . telegram_html($username) . ' (<code>' . (int)$purchase['telegram_user_id'] . "</code>)\n"
        . '<b>Paket:</b> ' . (int)$purchase['amount'] . " token\n"
        . '<b>Nominal:</b> ' . telegram_rupiah((int)$purchase['price_rupiah']) . "\n"
        . '<b>Status:</b> ' . $statusLabel;
}

/**
 * Log terstruktur untuk kegagalan alur Telegram.
 *
 * Menuliskan kelas exception, pesan, file:line, SQLSTATE/driver error (bila PDOException),
 * konteks tambahan, dan stack trace ke error log PHP sehingga penyebab kegagalan approve
 * dapat ditelusuri tanpa hanya bergantung pada pesan di Telegram.
 */
function telegram_log_exception(string $stage, Throwable $e, array $context = []): void
{
    $payload = [
        'stage' => $stage,
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'code' => (string)$e->getCode(),
        'origin' => $e->getFile() . ':' . $e->getLine(),
    ];

    if ($e instanceof PDOException) {
        $payload['sqlstate'] = (string)($e->errorInfo[0] ?? '');
        $payload['driver_code'] = (string)($e->errorInfo[1] ?? '');
        $payload['driver_message'] = (string)($e->errorInfo[2] ?? '');
    }

    foreach ($context as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $payload['ctx_' . $key] = (string)$value;
        } else {
            $payload['ctx_' . $key] = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    $previous = $e->getPrevious();
    if ($previous instanceof Throwable) {
        $payload['previous'] = get_class($previous) . ': ' . $previous->getMessage()
            . ' @ ' . $previous->getFile() . ':' . $previous->getLine();
    }

    error_log('[telegram] ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    error_log('[telegram] stage=' . $stage . ' trace: ' . $e->getTraceAsString());
}


