<?php
// ===== FORCE NO CACHE (ANTI DELAY STATISTIK) =====
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Headers lainnya
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/jwt_middleware.php';

    // Check PDO connection
    if (!isset($pdo)) {
        throw new Exception('Database connection failed');
    }

    $authUser = optional_auth();
    $scope = $_GET['scope'] ?? 'auto';

    // If request comes from a logged in regular user and scope is not explicitly 'global'
    if ($authUser && !empty($authUser['id']) && $scope !== 'global' && ($authUser['role'] ?? 'user') !== 'admin') {
        $userId = (int)$authUser['id'];

        // Get total user journals & views
        $stmtJ = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(views), 0) as views FROM journals WHERE user_id = ?");
        $stmtJ->execute([$userId]);
        $jData = $stmtJ->fetch(PDO::FETCH_ASSOC);

        // Get total user opinions & views
        $stmtO = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(views), 0) as views FROM opinions WHERE user_id = ?");
        $stmtO->execute([$userId]);
        $oData = $stmtO->fetch(PDO::FETCH_ASSOC);

        $userJournals = (int)($jData['total'] ?? 0);
        $userOpinions = (int)($oData['total'] ?? 0);
        $totalArticles = $userJournals + $userOpinions;
        $totalViews = (int)($jData['views'] ?? 0) + (int)($oData['views'] ?? 0);

        echo json_encode([
            'ok' => true,
            'is_personal' => true,
            'stats' => [
                'total_journals' => $userJournals,
                'total_opinions' => $userOpinions,
                'total_articles' => $totalArticles,
                'total_views'    => $totalViews,
                'total_visitors' => $totalViews
            ]
        ]);
        exit;
    }

    // Global Stats Fallback (Admin / Public / Unauthenticated)
    $stmtJournals = $pdo->query("SELECT COUNT(*) as total FROM journals WHERE status = 'published'");
    $totalJournals = $stmtJournals->fetch(PDO::FETCH_ASSOC)['total'];

    $stmtOpinions = $pdo->query("SELECT COUNT(*) as total FROM opinions WHERE status = 'published'");
    $totalOpinions = $stmtOpinions->fetch(PDO::FETCH_ASSOC)['total'];

    $stmtViewsJ = $pdo->query("SELECT COALESCE(SUM(views), 0) as total FROM journals WHERE status = 'published'");
    $viewsJournals = $stmtViewsJ->fetch(PDO::FETCH_ASSOC)['total'];

    $stmtViewsO = $pdo->query("SELECT COALESCE(SUM(views), 0) as total FROM opinions WHERE status = 'published'");
    $viewsOpinions = $stmtViewsO->fetch(PDO::FETCH_ASSOC)['total'];

    $totalViews = $viewsJournals + $viewsOpinions;

    $stmtVisitors = $pdo->query("SELECT COUNT(DISTINCT ip_address) as total FROM visitors");
    $totalVisitors = $stmtVisitors->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'ok' => true,
        'is_personal' => false,
        'stats' => [
            'total_journals' => (int)$totalJournals,
            'total_opinions' => (int)$totalOpinions,
            'total_articles' => (int)($totalJournals + $totalOpinions),
            'total_views' => (int)$totalViews,
            'total_visitors' => (int)$totalVisitors
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
