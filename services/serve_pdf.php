<?php
// serve_pdf.php - streaming PDF dari folder uploads secara aman.
//
// Endpoint ini HANYA boleh menyajikan berkas .pdf yang benar-benar berada di
// dalam direktori uploads. Semua input dari user direduksi menjadi nama berkas
// (basename) lalu diverifikasi ulang dengan realpath, sehingga path apa pun di
// luar uploads (mis. /.env, /services/db.php) tidak dapat dijangkau.

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

// Endpoint ini menyajikan berkas, bukan JSON.
header_remove('Content-Type');

$requested = isset($_GET['file']) ? (string)$_GET['file'] : '';

if ($requested === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing file parameter';
    exit;
}

// Buang query string / fragment bila URL lengkap yang dikirim, lalu ambil
// hanya nama berkasnya. Ini otomatis mematikan seluruh varian traversal
// ("../", "..\\", "%2e%2e", absolute path, dst).
$path = parse_url($requested, PHP_URL_PATH);
if ($path === false || $path === null) {
    $path = $requested;
}
$filename = basename(str_replace('\\', '/', $path));

// Hanya izinkan pola nama berkas yang wajar dan berekstensi .pdf.
if (!preg_match('/^[A-Za-z0-9._-]+\.pdf$/i', $filename)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid file name';
    exit;
}

$uploadDir = realpath(rtrim(UPLOAD_DIR_ABS, '/\\'));
$filepath  = $uploadDir === false ? false : realpath($uploadDir . DIRECTORY_SEPARATOR . $filename);

// Verifikasi akhir: hasil realpath wajib berada di bawah direktori uploads dan
// merupakan berkas biasa (bukan direktori / symlink ke luar).
if (
    $uploadDir === false
    || $filepath === false
    || strpos($filepath, $uploadDir . DIRECTORY_SEPARATOR) !== 0
    || !is_file($filepath)
) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File not found';
    exit;
}

// Paksa browser preview PDF.
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');
header('Content-Length: ' . filesize($filepath));
header('X-Content-Type-Options: nosniff');

readfile($filepath);
exit;
