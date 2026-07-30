<?php
// services/admin/token_transactions.php
// Riwayat transaksi token seluruh user (ledger) untuk panel admin.
// Method: GET -> ?type=purchase|upload|refund|adjustment &user_id= &limit= &offset=

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/jwt_middleware.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'message' => 'Only GET allowed']);
    exit;
}

try {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $type = strtolower(trim((string)($_GET['type'] ?? '')));
    $userId = max(0, (int)($_GET['user_id'] ?? 0));

    $allowedTypes = ['purchase', 'upload', 'refund', 'adjustment'];
    if ($type !== '' && !in_array($type, $allowedTypes, true)) {
        throw new InvalidArgumentException('Filter tipe transaksi tidak valid.');
    }

    $conditions = [];
    $params = [];
    if ($type !== '') {
        $conditions[] = 'tt.type = ?';
        $params[] = $type;
    }
    if ($userId > 0) {
        $conditions[] = 'tt.user_id = ?';
        $params[] = $userId;
    }
    $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM token_transactions tt' . $where);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT tt.id, tt.user_id, tt.type, tt.amount, tt.balance_after,
                tt.reference_type, tt.reference_id, tt.status, tt.description,
                tt.created_at, tt.processed_at, tt.processed_by_telegram_id,
                u.name AS user_name, u.email AS user_email,
                admin.name AS processed_by_name
         FROM token_transactions tt
         LEFT JOIN users u ON u.id = tt.user_id
         LEFT JOIN users admin ON admin.id = tt.processed_by' . $where .
        " ORDER BY tt.created_at DESC, tt.id DESC LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);

    // Ringkasan global: token masuk (kredit) vs terpakai (debit) + saldo beredar.
    $summary = $pdo->query(
        "SELECT
            COALESCE(SUM(CASE WHEN amount > 0 THEN amount END), 0) AS credited,
            COALESCE(SUM(CASE WHEN amount < 0 THEN -amount END), 0) AS debited,
            COUNT(*) AS transactions
         FROM token_transactions"
    )->fetch();

    $circulating = (int)$pdo->query(
        'SELECT COALESCE(SUM(balance), 0) FROM user_token_wallets'
    )->fetchColumn();

    echo json_encode([
        'ok' => true,
        'results' => array_map(static function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'userId' => (int)$row['user_id'],
                'userName' => $row['user_name'],
                'userEmail' => $row['user_email'],
                'type' => $row['type'],
                'amount' => (int)$row['amount'],
                'balanceAfter' => (int)$row['balance_after'],
                'referenceType' => $row['reference_type'],
                'referenceId' => $row['reference_id'] === null ? null : (int)$row['reference_id'],
                'status' => $row['status'],
                'description' => $row['description'],
                'createdAt' => $row['created_at'],
                'processedAt' => $row['processed_at'],
                'processedByName' => $row['processed_by_name'],
                'processedByTelegramId' => $row['processed_by_telegram_id'] === null
                    ? null
                    : (int)$row['processed_by_telegram_id'],
            ];
        }, $stmt->fetchAll()),
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'summary' => [
            'credited' => (int)($summary['credited'] ?? 0),
            'debited' => (int)($summary['debited'] ?? 0),
            'transactions' => (int)($summary['transactions'] ?? 0),
            'circulating' => $circulating,
        ],
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Admin token transactions error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Gagal memuat riwayat transaksi token.']);
}
