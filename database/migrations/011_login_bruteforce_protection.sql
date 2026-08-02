-- KSM Education - Login brute-force protection migration
-- Target: MySQL 5.7+ / MariaDB 10.2+
-- Apply after 001-010. No request-time schema changes are used.
--
-- Tabel ini menyimpan HASH (sha256) alamat IP dan email, mengikuti pola yang
-- sudah dipakai email_otp_ip_attempts.ip_hash dan contact_messages.ip_hash,
-- sehingga tidak ada IP atau email mentah tambahan yang tersimpan di database.
--
-- Dipakai oleh services/login_guard.php untuk:
--   1. membatasi jumlah kegagalan login per IP   (semua akun dari satu sumber)
--   2. membatasi jumlah kegagalan login per email (satu akun dari banyak IP)
-- Kolom context memisahkan hitungan area user dan panel admin agar serangan
-- ke panel admin dapat dibatasi lebih ketat tanpa mengganggu login pengguna.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash CHAR(64) NOT NULL,
  email_hash CHAR(64) NOT NULL,
  context ENUM('user','admin') NOT NULL DEFAULT 'user',
  successful TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempts_ip (ip_hash, context, successful, created_at),
  KEY idx_login_attempts_email (email_hash, context, successful, created_at),
  KEY idx_login_attempts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
