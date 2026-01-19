<?php
// config/database.example.php
// TEMPLATE FILE - Copy this file to database.php and fill with your credentials

/**
 * Database Configuration Template
 * 
 * SETUP INSTRUCTIONS:
 * 1. Copy this file: cp database.example.php database.php
 * 2. Edit database.php with your actual database credentials
 * 3. NEVER commit database.php to Git (already in .gitignore)
 */

// Database Host (usually 'localhost' for local development)
$DB_HOST = 'localhost';

// Database Name (create this database first in phpMyAdmin/MySQL)
$DB_NAME = 'journal_system2';

// Database Username (usually 'root' for local XAMPP)
$DB_USER = 'root';

// Database Password (empty for local XAMPP, set for production)
$DB_PASS = '';

/**
 * PRODUCTION NOTES:
 * - Change $DB_HOST to your hosting database host
 * - Use strong password for production
 * - Database name may have prefix (e.g., username_journal_system2)
 * - Username may have prefix (e.g., username_dbuser)
 */

/**
 * SECURITY REMINDER:
 * - database.php is already in .gitignore
 * - NEVER share database.php publicly
 * - Use different credentials for development vs production
 */
?>