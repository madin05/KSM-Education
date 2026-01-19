<?php
// config/app.php

// Application Info
define('APP_NAME', 'Journal Management System');
define('APP_VERSION', '1.0.0');
define('API_VERSION', 'v1');

// Paths
define('BASE_PATH', __DIR__ . '/..');
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('LOG_PATH', BASE_PATH . '/logs');

// Upload Settings
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB
define('ALLOWED_PDF_SIZE', 20 * 1024 * 1024);
define('ALLOWED_IMAGE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png']);

// Security
define('SESSION_LIFETIME', 3600); // 1 hour
define('BCRYPT_COST', 12);

// Pagination
define('DEFAULT_LIMIT', 10);
define('MAX_LIMIT', 100);
?>
