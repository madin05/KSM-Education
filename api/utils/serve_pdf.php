<?php
// api/utils/serve_pdf.php

// PUBLIC ACCESS - No auth required (user bisa download tanpa login)

$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(400);
    echo 'File parameter required';
    exit;
}

// Security: prevent directory traversal
$file = basename($file);

// Path ke uploads folder
$filepath = __DIR__ . '/../../uploads/' . $file;

// Check if file exists
if (!file_exists($filepath)) {
    http_response_code(404);
    echo 'File not found: ' . htmlspecialchars($file);
    exit;
}

// Validate file extension (security)
$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    http_response_code(403);
    echo 'Only PDF files are allowed';
    exit;
}

// Serve PDF file
header_remove('Content-Type');
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($filepath) . '"');
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');
header('Content-Length: ' . filesize($filepath));
header('X-Content-Type-Options: nosniff');

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

readfile($filepath);
exit;
?>