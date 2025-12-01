<?php
// db.php
header('Content-Type: application/json; charset=utf-8');
// optionally set CORS for cross-origin dev (adjust origin for security)
// header('Access-Control-Allow-Origin: http://localhost:3000');
// header('Access-Control-Allow-Credentials: true');

define('DB_PORT','3306');  
define('DB_NAME','journal_system2'); 
$DB_HOST = 'localhost';
$DB_NAME = 'journal_system2';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'DB connection failed']);
    exit;
}
