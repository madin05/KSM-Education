<?php
// api/stats/get.php

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../database/db.php';

try {
    // Get total journals
    $stmtJournals = $pdo->query("SELECT COUNT(*) as total FROM journals");
    $totalJournals = $stmtJournals->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total opinions
    $stmtOpinions = $pdo->query("SELECT COUNT(*) as total FROM opinions");
    $totalOpinions = $stmtOpinions->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total views from journals
    $stmtViewsJ = $pdo->query("SELECT COALESCE(SUM(views), 0) as total FROM journals");
    $viewsJournals = $stmtViewsJ->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total views from opinions
    $stmtViewsO = $pdo->query("SELECT COALESCE(SUM(views), 0) as total FROM opinions");
    $viewsOpinions = $stmtViewsO->fetch(PDO::FETCH_ASSOC)['total'];

    $totalViews = $viewsJournals + $viewsOpinions;

    // Get total unique visitors
    $totalVisitors = 0;
    $checkTable = $pdo->query("SHOW TABLES LIKE 'visitors'");
    if ($checkTable->rowCount() > 0) {
        $stmtVisitors = $pdo->query("SELECT COUNT(DISTINCT ip_address) as total FROM visitors");
        $totalVisitors = $stmtVisitors->fetch(PDO::FETCH_ASSOC)['total'];
    }

    echo json_encode([
        'ok' => true,
        'stats' => [
            'total_journals' => (int)$totalJournals,
            'total_opinions' => (int)$totalOpinions,
            'total_articles' => (int)($totalJournals + $totalOpinions),
            'total_views' => (int)$totalViews,
            'total_visitors' => (int)$totalVisitors
        ]
    ]);
} catch (PDOException $e) {
    error_log('Get stats error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Server error'
    ]);
} catch (Exception $e) {
    error_log('Get stats error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}
