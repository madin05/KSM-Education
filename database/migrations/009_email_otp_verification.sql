-- KSM Education - Email OTP verification migration
-- Target: MySQL 5.7+ / MariaDB 10.2+
-- Apply after 001-008. No request-time schema changes are used.

SET NAMES utf8mb4;

-- users.email_verified_at menandai email yang sudah lolos verifikasi OTP.
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_verified_at') = 0,
  'ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER email', 'SELECT 1');
PREPARE otp_stmt FROM @sql; EXECUTE otp_stmt; DEALLOCATE PREPARE otp_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_email_verified_at') = 0,
  'ALTER TABLE users ADD INDEX idx_users_email_verified_at (email_verified_at)', 'SELECT 1');
PREPARE otp_stmt FROM @sql; EXECUTE otp_stmt; DEALLOCATE PREPARE otp_stmt;

-- Backfill wajib: akun lama dibuat sebelum fitur OTP ada dan tidak boleh
-- kehilangan akses login setelah migrasi ini dijalankan.
UPDATE users SET email_verified_at = COALESCE(created_at, CURRENT_TIMESTAMP) WHERE email_verified_at IS NULL;

CREATE TABLE IF NOT EXISTS email_verifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  resend_count INT UNSIGNED NOT NULL DEFAULT 0,
  consumed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_verifications_user_created (user_id, created_at),
  KEY idx_email_verifications_expires (expires_at),
  CONSTRAINT email_verifications_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
