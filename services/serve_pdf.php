<?php
// serve_pdf.php — Serves PDF files with correct headers for inline preview & download
error_reporting(0);
ini_set('display_errors', 0);

// Compute APP_ROOT directly (avoids including db.php which sets Content-Type: application/json)
$_abs      = realpath(__FILE__);
$_docroot  = realpath($_SERVER['DOCUMENT_ROOT']);
$_rel      = str_replace('\\', '/', str_replace($_docroot, '', $_abs));
$_app_root = dirname(dirname($_rel)); // e.g. /ksmaja (services/serve_pdf.php → 2 levels up)
$APP_ROOT  = ($_app_root === '/' || $_app_root === '.') ? '' : $_app_root;

header('Access-Control-Allow-Origin: *');

// Read ?file= parameter (e.g. /ksmaja/uploads/abc.pdf or /uploads/abc.pdf)
$file = isset($_GET['file']) ? $_GET['file'] : '';

// Decode base64 if needed to hide .pdf extension from download managers
if ($file && preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $file)) {
    $decoded = base64_decode($file, true);
    if ($decoded !== false && (strpos($decoded, '/') !== false || strpos($decoded, '\\') !== false)) {
        $file = $decoded;
    }
}

// Basic security — block empty or path-traversal
if (!$file || strpos($file, '..') !== false) {
    http_response_code(403);
    echo 'Invalid file path';
    exit;
}

// Normalize: ensure path starts with APP_ROOT prefix
// Old DB records store bare /uploads/abc.pdf — prepend APP_ROOT
// New records already have /ksmaja/uploads/abc.pdf — leave as-is
if ($APP_ROOT && strpos($file, $APP_ROOT) !== 0) {
    $file = $APP_ROOT . '/' . ltrim($file, '/');
}

// Absolute filesystem path
$filepath = $_SERVER['DOCUMENT_ROOT'] . $file;

if (!file_exists($filepath)) {
    http_response_code(404);
    echo 'File not found: ' . htmlspecialchars($file);
    exit;
}

// Remove global headers set by Apache (.htaccess) that would block iframe or PDF plugins
header_remove('Content-Type');
header_remove('X-Frame-Options');
header_remove('Content-Security-Policy');

// Serve with correct PDF headers
if (isset($_GET['preview']) && $_GET['preview'] == '1') {
    header('Content-Type: application/octet-stream');
} else {
    header('Content-Type: application/pdf');
}
header('Content-Disposition: inline; filename="' . basename($filepath) . '"');
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');
header('Content-Length: ' . filesize($filepath));
header('X-Content-Type-Options: nosniff');

readfile($filepath);
exit;
