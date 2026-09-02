<?php
/**
 * services/midtrans_create_transaction.php
 * Create Midtrans Snap Transaction for Token Purchase.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/midtrans_helper.php';
require_once __DIR__ . '/token_service.php';

$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;

    $packageId = trim((string)($data['package_id'] ?? ''));
    $customTokens = (int)($data['tokens'] ?? 0);

    $packages = midtrans_get_token_packages();

    $tokens = 0;
    $priceRupiah = 0;
    $packageTitle = 'Pembelian Token';

    if (!empty($packageId) && isset($packages[$packageId])) {
        $pkg = $packages[$packageId];
        $tokens = $pkg['tokens'];
        $priceRupiah = $pkg['price'];
        $packageTitle = "Paket Token {$pkg['title']} ({$tokens} Token)";
    } elseif ($customTokens > 0) {
        $tokens = $customTokens;
        // Default pricing calculation if custom: Rp 2.000 / token
        $priceRupiah = $tokens * 2000;
        $packageTitle = "Paket {$tokens} Token";
    } else {
        throw new InvalidArgumentException('Pilihan paket token tidak valid.');
    }

    $publicId = token_generate_public_id();
    $midtransOrderId = 'TOK-MID-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 6);

    // Save pending request to database
    $stmt = $pdo->prepare(
        "INSERT INTO token_purchase_requests
           (public_id, midtrans_order_id, user_id, amount, price_rupiah, status, created_at)
         VALUES (?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)"
    );
    $stmt->execute([
        $publicId,
        $midtransOrderId,
        (int)$user['id'],
        $tokens,
        $priceRupiah,
    ]);

    $requestId = (int)$pdo->lastInsertId();

    // Prepare Midtrans Payload
    $config = midtrans_get_config();
    $payload = [
        'transaction_details' => [
            'order_id' => $midtransOrderId,
            'gross_amount' => $priceRupiah,
        ],
        'item_details' => [
            [
                'id' => 'TOK-' . $tokens,
                'price' => $priceRupiah,
                'quantity' => 1,
                'name' => "KSM Education: {$tokens} Token Upload",
            ]
        ],
        'customer_details' => [
            'first_name' => $user['name'] ?? 'User KSM',
            'email' => $user['email'] ?? 'user@ksmeducation.com',
        ],
        'callbacks' => [
            'finish' => (defined('APP_URL') ? APP_URL : '') . '/user/token_history_user.php',
        ]
    ];

    // Request Snap Token
    $snapResult = midtrans_create_snap_transaction($payload);

    // Update snap_token in DB
    $stmt = $pdo->prepare(
        'UPDATE token_purchase_requests
         SET snap_token = ?
         WHERE id = ?'
    );
    $stmt->execute([$snapResult['token'], $requestId]);

    echo json_encode([
        'ok' => true,
        'snap_token' => $snapResult['token'],
        'redirect_url' => $snapResult['redirect_url'],
        'order_id' => $midtransOrderId,
        'public_id' => $publicId,
        'tokens' => $tokens,
        'price' => $priceRupiah,
        'client_key' => $config['client_key'],
        'snap_js_url' => $config['snap_js_url'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Gagal memproses pembayaran Midtrans: ' . $e->getMessage(),
    ]);
}
