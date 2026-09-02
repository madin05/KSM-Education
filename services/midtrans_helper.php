<?php
/**
 * services/midtrans_helper.php
 * Helper library for Midtrans Payment Gateway Integration.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/env_loader.php';

function midtrans_get_config(): array
{
    $serverKey = trim((string)get_env_var('MIDTRANS_SERVER_KEY', 'SB-Mid-server-YOUR_SERVER_KEY'));
    $clientKey = trim((string)get_env_var('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-YOUR_CLIENT_KEY'));
    $merchantId = trim((string)get_env_var('MIDTRANS_MERCHANT_ID', ''));
    $isProduction = filter_var(get_env_var('MIDTRANS_IS_PRODUCTION', 'false'), FILTER_VALIDATE_BOOLEAN);

    $snapApiUrl = $isProduction
        ? 'https://app.midtrans.com/snap/v1/transactions'
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

    $snapJsUrl = $isProduction
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js';

    return [
        'server_key' => $serverKey,
        'client_key' => $clientKey,
        'merchant_id' => $merchantId,
        'is_production' => $isProduction,
        'snap_api_url' => $snapApiUrl,
        'snap_js_url' => $snapJsUrl,
    ];
}

/**
 * Request Snap Token & Redirect URL from Midtrans API.
 */
function midtrans_create_snap_transaction(array $payload): array
{
    $config = midtrans_get_config();
    if (empty($config['server_key']) || str_contains($config['server_key'], 'YOUR_SERVER_KEY')) {
        throw new RuntimeException('Midtrans Server Key belum dikonfigurasi di file .env.');
    }

    $authHeader = 'Basic ' . base64_encode($config['server_key'] . ':');

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $config['snap_api_url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: ' . $authHeader,
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException('Koneksi ke Midtrans gagal: ' . $err);
    }

    $result = json_decode($response, true);

    if ($httpCode >= 400 || empty($result['token'])) {
        $msg = $result['error_messages'][0] ?? ($result['message'] ?? 'Gagal membuat transaksi Midtrans.');
        throw new RuntimeException('Midtrans error (' . $httpCode . '): ' . $msg);
    }

    return [
        'token' => $result['token'],
        'redirect_url' => $result['redirect_url'] ?? '',
    ];
}

/**
 * Verify Webhook Signature Key from Midtrans.
 * Signature formula: sha512(order_id + status_code + gross_amount + server_key)
 */
function midtrans_verify_signature(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool
{
    $config = midtrans_get_config();
    $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $config['server_key']);
    return hash_equals($expectedSignature, $signatureKey);
}

/**
 * Predefined Token Packages
 */
function midtrans_get_token_packages(): array
{
    return [
        'pkg_1' => [
            'id' => 'pkg_1',
            'tokens' => 1,
            'price' => 2500,
            'title' => 'Eceran',
            'tag' => 'Trial',
            'popular' => false,
            'per_token' => 2500,
            'discount' => 0
        ],
        'pkg_5' => [
            'id' => 'pkg_5',
            'tokens' => 5,
            'price' => 10000,
            'title' => 'Hemat',
            'tag' => 'Hemat 20%',
            'popular' => false,
            'per_token' => 2000,
            'discount' => 20
        ],
        'pkg_10' => [
            'id' => 'pkg_10',
            'tokens' => 10,
            'price' => 18000,
            'title' => 'Standar',
            'tag' => 'Paling Populer',
            'popular' => true,
            'per_token' => 1800,
            'discount' => 28
        ],
        'pkg_20' => [
            'id' => 'pkg_20',
            'tokens' => 20,
            'price' => 34000,
            'title' => 'Super',
            'tag' => 'Best Value',
            'popular' => false,
            'per_token' => 1700,
            'discount' => 32
        ],
        'pkg_50' => [
            'id' => 'pkg_50',
            'tokens' => 50,
            'price' => 80000,
            'title' => 'Sultan',
            'tag' => 'Diskon 36%',
            'popular' => false,
            'per_token' => 1600,
            'discount' => 36
        ],
    ];
}
