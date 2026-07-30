<?php
// services/admin/dashboard_stats.php
// Ringkasan operasional untuk dashboard admin (satu request, banyak agregat).
// Method: GET
//
// Menyatukan angka dari beberapa tabel supaya dashboard tidak lagi hanya
// menampilkan jumlah artikel + pengunjung:
//   - journals / opinions  : pending review & published
//   - contact_messages     : pesan baru / dibalas
//   - token_purchase_requests + token_transactions : top-up menunggu verifikasi
//   - visitors             : kunjungan hari ini & 7 hari terakhir

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/jwt_middleware.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'message' => 'Only GET allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/**
 * Jalankan query agregat sederhana; kembalikan 0 bila tabel belum ada
 * (misal migrasi opsional belum dijalankan) agar dashboard tetap tampil.
 */
function admin_stat_scalar(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

try {
    // --- Konten ---
    $journalsPending = admin_stat_scalar($pdo, "SELECT COUNT(*) FROM journals WHERE status = 'pending'");
    $journalsPublished = admin_stat_scalar($pdo, "SELECT COUNT(*) FROM journals WHERE status = 'published'");
    $journalsRejected = admin_stat_scalar($pdo, "SELECT COUNT(*) FROM journals WHERE status = 'rejected'");

    $opinionsPending = admin_stat_scalar($pdo, "SELECT COUNT(*) FROM opinions WHERE status = 'pending'");
    $opinionsPublished = admin_stat_scalar($pdo, "SELECT COUNT(*) FROM opinions WHERE status = 'published'");
    $opinionsRejected = admin_stat_scalar($pdo, "SELECT COUNT(*) FROM opinions WHERE status = 'rejected'");

    $viewsJournals = admin_stat_scalar($pdo, "SELECT COALESCE(SUM(views), 0) FROM journals WHERE status = 'published'");
    $viewsOpinions = admin_stat_scalar($pdo, "SELECT COALESCE(SUM(views), 0) FROM opinions WHERE status = 'published'");

    // --- Kotak masuk kontak ---
    $contactNew = admin_stat_scalar($pdo, "SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
    $contactOpen = admin_stat_scalar($pdo, "SELECT COUNT(*) FROM contact_messages WHERE status IN ('new','read')");
    $contactTotal = admin_stat_scalar($pdo, 'SELECT COUNT(*) FROM contact_messages');

    // --- Token ---
    $tokenPending = admin_stat_scalar(
        $pdo,
        "SELECT COUNT(*) FROM token_purchase_requests WHERE status IN ('pending','awaiting_proof')"
    );
    $tokenPendingAmount = admin_stat_scalar(
        $pdo,
        "SELECT COALESCE(SUM(amount), 0) FROM token_purchase_requests WHERE status IN ('pending','awaiting_proof')"
    );
    $tokenBalance = admin_stat_scalar($pdo, 'SELECT COALESCE(SUM(balance), 0) FROM user_token_wallets');
    $tokenTxToday = admin_stat_scalar(
        $pdo,
        'SELECT COUNT(*) FROM token_transactions WHERE DATE(created_at) = CURDATE()'
    );

    // --- Pengguna ---
    $usersTotal = admin_stat_scalar($pdo, 'SELECT COUNT(*) FROM users');
    $usersNew7d = admin_stat_scalar(
        $pdo,
        'SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'
    );

    // --- Pengunjung ---
    $visitorsToday = admin_stat_scalar($pdo, 'SELECT COUNT(*) FROM visitors WHERE DATE(visited_at) = CURDATE()');
    $visitors7d = admin_stat_scalar(
        $pdo,
        'SELECT COUNT(*) FROM visitors WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'
    );
    $visitorsUnique = admin_stat_scalar($pdo, 'SELECT COUNT(DISTINCT ip_address) FROM visitors');
    $visitorsTotal = admin_stat_scalar($pdo, 'SELECT COUNT(*) FROM visitors');

    echo json_encode([
        'ok' => true,
        'stats' => [
            'content' => [
                'journalsPending' => $journalsPending,
                'journalsPublished' => $journalsPublished,
                'journalsRejected' => $journalsRejected,
                'opinionsPending' => $opinionsPending,
                'opinionsPublished' => $opinionsPublished,
                'opinionsRejected' => $opinionsRejected,
                'reviewPending' => $journalsPending + $opinionsPending,
                'articlesPublished' => $journalsPublished + $opinionsPublished,
                'totalViews' => $viewsJournals + $viewsOpinions,
            ],
            'contact' => [
                'new' => $contactNew,
                'open' => $contactOpen,
                'total' => $contactTotal,
            ],
            'token' => [
                'pendingRequests' => $tokenPending,
                'pendingAmount' => $tokenPendingAmount,
                'circulatingBalance' => $tokenBalance,
                'transactionsToday' => $tokenTxToday,
            ],
            'users' => [
                'total' => $usersTotal,
                'new7d' => $usersNew7d,
            ],
            'visitors' => [
                'today' => $visitorsToday,
                'last7d' => $visitors7d,
                'unique' => $visitorsUnique,
                'total' => $visitorsTotal,
            ],
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Gagal memuat ringkasan dashboard.']);
}
