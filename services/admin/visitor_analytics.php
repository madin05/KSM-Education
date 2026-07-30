<?php
// services/admin/visitor_analytics.php
// Detail analitik pengunjung untuk panel admin.
// Method: GET -> ?days=7|30|90 (default 30)
//
// Sumber data: tabel `visitors` (migrasi 005_phase5_visitor_analytics.sql) yang
// diisi oleh services/track_visitor.php. Sebelumnya data ini hanya dipakai
// untuk satu angka di dashboard, sekarang diagregasi lengkap.

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/jwt_middleware.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'message' => 'Only GET allowed']);
    exit;
}

/**
 * Klasifikasi perangkat sederhana dari user agent.
 * Dilakukan di PHP agar query tetap portabel antara MySQL dan MariaDB.
 */
function visitor_device_label(?string $userAgent): string
{
    $ua = strtolower((string)$userAgent);
    if ($ua === '') {
        return 'Tidak diketahui';
    }
    if (preg_match('/(bot|crawler|spider|slurp|bingpreview)/', $ua)) {
        return 'Bot / Crawler';
    }
    if (preg_match('/(ipad|tablet|playbook|silk)/', $ua)) {
        return 'Tablet';
    }
    if (preg_match('/(mobile|iphone|ipod|android.*mobile|windows phone)/', $ua)) {
        return 'Mobile';
    }
    return 'Desktop';
}

try {
    $days = (int)($_GET['days'] ?? 30);
    if (!in_array($days, [7, 30, 90], true)) {
        $days = 30;
    }

    // --- Ringkasan ---
    $summary = $pdo->query(
        'SELECT
            COUNT(*) AS total_visits,
            COUNT(DISTINCT ip_address) AS unique_visitors,
            MIN(visited_at) AS first_visit,
            MAX(visited_at) AS last_visit
         FROM visitors'
    )->fetch();

    $todayStmt = $pdo->query(
        'SELECT COUNT(*) AS visits, COUNT(DISTINCT ip_address) AS uniques
         FROM visitors WHERE DATE(visited_at) = CURDATE()'
    )->fetch();

    $rangeStmt = $pdo->prepare(
        'SELECT COUNT(*) AS visits, COUNT(DISTINCT ip_address) AS uniques
         FROM visitors
         WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)'
    );
    $rangeStmt->execute([$days]);
    $range = $rangeStmt->fetch();

    // --- Tren harian (diisi nol untuk tanggal tanpa kunjungan) ---
    $dailyStmt = $pdo->prepare(
        'SELECT DATE(visited_at) AS visit_date,
                COUNT(*) AS visits,
                COUNT(DISTINCT ip_address) AS uniques
         FROM visitors
         WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(visited_at)
         ORDER BY visit_date ASC'
    );
    $dailyStmt->execute([$days]);

    $dailyMap = [];
    foreach ($dailyStmt->fetchAll() as $row) {
        $dailyMap[$row['visit_date']] = [
            'visits' => (int)$row['visits'],
            'uniques' => (int)$row['uniques'],
        ];
    }

    $daily = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} day"));
        $daily[] = [
            'date' => $date,
            'visits' => $dailyMap[$date]['visits'] ?? 0,
            'uniques' => $dailyMap[$date]['uniques'] ?? 0,
        ];
    }

    // --- Halaman terpopuler ---
    $pagesStmt = $pdo->prepare(
        'SELECT COALESCE(NULLIF(page_url, ""), "(tidak dicatat)") AS page_url,
                COUNT(*) AS visits,
                COUNT(DISTINCT ip_address) AS uniques
         FROM visitors
         WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY page_url
         ORDER BY visits DESC
         LIMIT 15'
    );
    $pagesStmt->execute([$days]);
    $topPages = array_map(static function (array $row): array {
        return [
            'page' => $row['page_url'],
            'visits' => (int)$row['visits'],
            'uniques' => (int)$row['uniques'],
        ];
    }, $pagesStmt->fetchAll());

    // --- Distribusi jam (pola jam sibuk) ---
    $hourlyStmt = $pdo->prepare(
        'SELECT HOUR(visited_at) AS hour_of_day, COUNT(*) AS visits
         FROM visitors
         WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY HOUR(visited_at)'
    );
    $hourlyStmt->execute([$days]);
    $hourlyMap = [];
    foreach ($hourlyStmt->fetchAll() as $row) {
        $hourlyMap[(int)$row['hour_of_day']] = (int)$row['visits'];
    }
    $hourly = [];
    for ($hour = 0; $hour < 24; $hour++) {
        $hourly[] = ['hour' => $hour, 'visits' => $hourlyMap[$hour] ?? 0];
    }

    // --- Perangkat (agregasi user agent) ---
    $deviceStmt = $pdo->prepare(
        'SELECT user_agent, COUNT(*) AS visits
         FROM visitors
         WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY user_agent'
    );
    $deviceStmt->execute([$days]);

    $devices = [];
    foreach ($deviceStmt->fetchAll() as $row) {
        $label = visitor_device_label($row['user_agent']);
        $devices[$label] = ($devices[$label] ?? 0) + (int)$row['visits'];
    }
    arsort($devices);
    $deviceList = [];
    foreach ($devices as $label => $visits) {
        $deviceList[] = ['label' => $label, 'visits' => $visits];
    }

    // --- Kunjungan terakhir ---
    $recentStmt = $pdo->query(
        'SELECT ip_address, user_agent, page_url, visited_at
         FROM visitors
         ORDER BY visited_at DESC, id DESC
         LIMIT 50'
    );
    $recent = array_map(static function (array $row): array {
        return [
            // IP disamarkan sebagian: cukup untuk membedakan pengunjung tanpa
            // memaparkan alamat lengkap di antarmuka admin.
            'ip' => preg_replace('/\.\d+$/', '.x', (string)$row['ip_address']),
            'device' => visitor_device_label($row['user_agent']),
            'page' => $row['page_url'],
            'visitedAt' => $row['visited_at'],
        ];
    }, $recentStmt->fetchAll());

    echo json_encode([
        'ok' => true,
        'days' => $days,
        'summary' => [
            'totalVisits' => (int)($summary['total_visits'] ?? 0),
            'uniqueVisitors' => (int)($summary['unique_visitors'] ?? 0),
            'firstVisit' => $summary['first_visit'] ?? null,
            'lastVisit' => $summary['last_visit'] ?? null,
            'todayVisits' => (int)($todayStmt['visits'] ?? 0),
            'todayUniques' => (int)($todayStmt['uniques'] ?? 0),
            'rangeVisits' => (int)($range['visits'] ?? 0),
            'rangeUniques' => (int)($range['uniques'] ?? 0),
        ],
        'daily' => $daily,
        'topPages' => $topPages,
        'hourly' => $hourly,
        'devices' => $deviceList,
        'recent' => $recent,
    ]);
} catch (Throwable $e) {
    error_log('Admin visitor analytics error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Gagal memuat analitik pengunjung.']);
}
