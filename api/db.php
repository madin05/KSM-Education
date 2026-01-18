<?php
// db.php

// Set header agar browser mengenali output sebagai JSON dengan encoding UTF-8
header('Content-Type: application/json; charset=utf-8');

// Opsi CORS (Cross-Origin Resource Sharing) untuk pengembangan (bisa di-uncomment jika butuh)
// header('Access-Control-Allow-Origin: http://localhost:3000');
// header('Access-Control-Allow-Credentials: true');

// Definisi konstanta dan variabel untuk koneksi database
define('DB_PORT', '3306');
define('DB_NAME', 'journal_system2');

$DB_HOST = 'localhost';
$DB_NAME = 'journal_system2';
$DB_USER = 'root';
$DB_PASS = '';

try {
    // Buat instance PDO baru untuk menghubungkan PHP ke MySQL
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        // Mode error: lemparkan exception jika ada kesalahan query
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Mode fetch default: kembalikan hasil query sebagai array asosiatif
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    // Jika koneksi gagal, set kode respon HTTP 500 (Server Error)
    http_response_code(500);

    // Kirim pesan error dalam format JSON dan hentikan eksekusi
    echo json_encode(['ok' => false, 'message' => 'DB connection failed']);
    exit;
}
