<?php
/**
 * admin/components/auth_guard.php
 *
 * Guard server-side untuk semua halaman di folder /admin.
 * Halaman hanya boleh dibuka oleh session dengan role 'admin' dan
 * account_status 'active'. Jika tidak, pengunjung dialihkan ke
 * halaman login admin. Harus di-include SEBELUM output HTML apa pun.
 */

if (defined('KSMEDU_ADMIN_GUARD')) {
    return;
}
define('KSMEDU_ADMIN_GUARD', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$ksmedu_admin_login_url = 'login_admin.php';

// Simpan halaman tujuan agar bisa dikembalikan setelah login berhasil.
$ksmedu_requested_page = basename($_SERVER['PHP_SELF'] ?? 'dashboard_admin.php');

$ksmedu_admin_ok = false;

if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    // Verifikasi ulang ke database: role/status bisa berubah setelah session dibuat.
    try {
        require_once __DIR__ . '/../../services/db.php';
        $ksmedu_stmt = $pdo->prepare(
            "SELECT role, account_status FROM users WHERE id = ? LIMIT 1"
        );
        $ksmedu_stmt->execute([(int)$_SESSION['user_id']]);
        $ksmedu_row = $ksmedu_stmt->fetch();
        $ksmedu_admin_ok = $ksmedu_row
            && ($ksmedu_row['role'] ?? '') === 'admin'
            && (($ksmedu_row['account_status'] ?? 'active') === 'active');
    } catch (Throwable $e) {
        error_log('Admin guard error: ' . $e->getMessage());
        $ksmedu_admin_ok = false;
    }
}

if (!$ksmedu_admin_ok) {
    // db.php mengirim header JSON; halaman admin adalah HTML biasa.
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('Location: ' . $ksmedu_admin_login_url . '?next=' . urlencode($ksmedu_requested_page));
    }
    exit;
}

// db.php men-set Content-Type JSON. Kembalikan ke HTML untuk halaman admin.
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

$admin_session_user = [
    'id'    => (int)$_SESSION['user_id'],
    'name'  => (string)($_SESSION['name'] ?? 'Admin'),
    'email' => (string)($_SESSION['email'] ?? ''),
    'role'  => 'admin',
];
