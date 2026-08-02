-- KSM Education - Refresh token rotation + reuse detection
-- Target: MySQL 5.7+ / MariaDB 10.2+
-- Apply after 001-011. Idempoten: aman dijalankan ulang, tidak menghapus data.
--
-- Latar belakang:
-- Sebelum migrasi ini, satu refresh token berlaku penuh 7 hari dan dapat
-- dipakai berkali-kali. Jika token tersebut dicuri (mis. lewat XSS pada
-- localStorage), penyerang bisa memperpanjang akses selama 7 hari tanpa
-- terdeteksi, dan pemilik akun tidak punya cara mencabutnya selain mengganti
-- password.
--
-- Dua kolom yang ditambahkan:
--   1. users.token_version — "generasi" sesi milik user. Setiap token membawa
--      klaim `tv`. Menaikkan nilai kolom ini mencabut SEMUA token (access dan
--      refresh) yang sudah beredar untuk user tersebut dalam satu operasi,
--      tanpa perlu mendaftar tiap jti ke blacklist.
--   2. jwt_blacklist.reason / revoked_at — membedakan token yang dicabut
--      karena logout dari token yang dicabut karena dirotasi. Pembedaan ini
--      yang membuat reuse detection mungkin: pemakaian ulang token ber-reason
--      'rotated' adalah indikasi kebocoran, sedangkan token 'logout' hanya
--      permintaan yang sudah kedaluwarsa secara normal.
--      revoked_at dipakai untuk grace window: rotasi yang baru terjadi
--      beberapa detik lalu diperlakukan sebagai request paralel (race antar
--      tab), bukan serangan.

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
-- users.token_version
-- -------------------------------------------------------------------------

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'token_version') = 0,
  'ALTER TABLE users ADD COLUMN token_version INT UNSIGNED NOT NULL DEFAULT 1',
  'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

-- Akun lama (kolom baru saja dibuat) mulai dari generasi 1 sehingga token
-- lama tanpa klaim `tv` masih diterima sampai kedaluwarsa sendiri.
UPDATE users SET token_version = 1 WHERE token_version IS NULL OR token_version = 0;

-- -------------------------------------------------------------------------
-- jwt_blacklist: alasan pencabutan + waktu pencabutan
-- -------------------------------------------------------------------------

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jwt_blacklist' AND COLUMN_NAME = 'reason') = 0,
  'ALTER TABLE jwt_blacklist ADD COLUMN reason VARCHAR(32) NOT NULL DEFAULT ''logout''',
  'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

-- DATETIME (bukan TIMESTAMP kedua) agar tidak bergantung pada dukungan
-- multiple auto-init TIMESTAMP di versi MySQL/MariaDB lama.
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jwt_blacklist' AND COLUMN_NAME = 'revoked_at') = 0,
  'ALTER TABLE jwt_blacklist ADD COLUMN revoked_at DATETIME NULL',
  'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jwt_blacklist' AND INDEX_NAME = 'idx_jwt_blacklist_reason') = 0,
  'ALTER TABLE jwt_blacklist ADD INDEX idx_jwt_blacklist_reason (reason, revoked_at)',
  'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
