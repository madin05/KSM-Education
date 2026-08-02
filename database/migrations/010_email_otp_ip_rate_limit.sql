-- KSM Education - Email OTP IP rate limiting migration
-- Target: MySQL 5.7+ / MariaDB 10.2+
-- Apply after 001-009. No request-time schema changes are used.
--
-- Tabel ini hanya menyimpan HASH alamat IP (sha256) mengikuti pola yang sudah
-- dipakai contact_messages.ip_hash dan password_reset_tokens.requested_ip_hash,
-- sehingga tidak ada IP mentah yang tersimpan di database.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS email_otp_ip_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash CHAR(64) NOT NULL,
  action_type ENUM('verify','resend') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_otp_ip_action_created (ip_hash, action_type, created_at),
  KEY idx_email_otp_ip_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
