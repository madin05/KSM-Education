<?php
/**
 * Shared helpers for KSM Education WhatsApp Bot Gateway (Fonnte / Generic API).
 */

require_once __DIR__ . '/db.php';

function wa_env(string $name, string $default = ''): string
{
    return trim((string)get_env_var($name, $default));
}

function wa_get_config(): array
{
    return [
        'bot_number' => preg_replace('/[^0-9]/', '', wa_env('WA_BOT_NUMBER', '6281234567890')),
        'api_key'    => wa_env('WA_API_KEY', ''),
        'provider'   => strtolower(wa_env('WA_PROVIDER', 'fonnte')),
        'packages'   => wa_env('WA_TOKEN_PACKAGES', '5:10000,10:18000,20:34000,50:80000'),
    ];
}

/**
 * Send WhatsApp text message via Gateway (Default: Fonnte API).
 */
function wa_send_message(string $targetNumber, string $message): bool
{
    $config = wa_get_config();
    if (empty($config['api_key'])) {
        error_log("[wa_helpers] WA_API_KEY is not configured.");
        return false;
    }

    $targetNumber = preg_replace('/[^0-9]/', '', $targetNumber);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'target' => $targetNumber,
            'message' => $message,
        ],
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $config['api_key']
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("[wa_helpers] cURL error sending WA message: " . $err);
        return false;
    }

    $result = json_decode($response, true);
    return !empty($result['status']);
}

/**
 * Format welcome & token package text matching Telegram bot 1:1.
 */
function wa_format_welcome_message(string $userName): string
{
    $config = wa_get_config();
    $raw = $config['packages'];
    
    $out = "Halo, *{$userName}*!\n\n";
    $out .= "Selamat datang di *KSM Education Token Shop*.\n\n";
    $out .= "Pilih paket token di bawah. Setelah memilih paket, transfer sesuai nominal dan kirim bukti pembayarannya di chat ini.\n\n";
    $out .= "🔒 _Saldo hanya ditambahkan setelah admin KSMedu menyetujui bukti transfer._\n\n";
    $out .= "📋 *PILIHAN PAKET TOKEN:*\n";

    $i = 1;
    foreach (explode(',', $raw) as $item) {
        $parts = array_map('trim', explode(':', $item, 2));
        if (count($parts) === 2) {
            $amount = (int)$parts[0];
            $price = (int)$parts[1];
            $out .= "{$i}️⃣ ⚡ *{$amount} Token* — Rp" . number_format($price, 0, ',', '.') . "\n";
            $i++;
        }
    }
    
    $out .= "\n👉 *Cara Pemesanan:*\nBalas dengan ketik angka paket (misal: *1*) atau langsung transfer & kirimkan foto/PDF bukti bayar Anda di sini.";
    return $out;
}

/**
 * Format order detail message matching Telegram order text.
 */
function wa_format_order_message(string $orderCode, int $tokenCount, int $totalPrice): string
{
    $out = "📌 *Pesanan {$orderCode}*\n\n";
    $out .= "Paket: *{$tokenCount} token*\n";
    $out .= "Total: *Rp" . number_format($totalPrice, 0, ',', '.') . "*\n\n";
    $out .= "Transfer sesuai nominal paket ke rekening/QRIS KSM Education.\n";
    $out .= "Setelah transfer, kirim foto atau PDF bukti pembayaran di chat ini.\n\n";
    $out .= "📸 Kirim foto atau PDF bukti transfer ke chat ini.";
    return $out;
}
