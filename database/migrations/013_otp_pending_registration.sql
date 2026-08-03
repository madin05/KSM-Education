-- KSM Education - Pending registration payload + reaktivasi akun via OTP
-- Target: MySQL 5.7+ / MariaDB 10.2+
-- Apply after 001-012. Idempoten: aman dijalankan ulang, tidak menghapus data.
--
-- Latar belakang:
-- Sebelum migrasi ini, registrasi ulang dengan email yang sudah ada selalu
-- dibalas generik tanpa efek apa pun. Akibatnya ada dua kondisi yang membuat
-- user terjebak tanpa penjelasan:
--   1. Akun belum terverifikasi yang OTP-nya kedaluwarsa. User yang menekan
--      "Daftar" lagi (bukan "Kirim Ulang OTP") mendapat pesan sukses, tetapi
--      tidak ada OTP baru yang diterbitkan.
--   2. Akun yang sudah di-soft-delete (account_status = 'deleted'). Emailnya
--      terkunci permanen oleh UNIQUE KEY users.email sehingga tidak bisa
--      dipakai mendaftar lagi.
--
-- Tiga kolom di bawah menyimpan "niat" registrasi ulang tersebut sampai OTP
-- terbukti dimiliki oleh pemilik email:
--   - pending_name / pending_password_hash: nama & password dari percobaan
--     registrasi terakhir. Baru ditulis ke tabel users setelah OTP benar,
--     sehingga pihak yang tidak memegang akses email tidak bisa mengubah
--     kredensial akun mana pun.
--   - is_reactivation: menandai OTP yang diterbitkan untuk menghidupkan
--     kembali akun 'deleted'. Tanpa penanda ini, jalur verifikasi tidak boleh
--     menyentuh akun non-aktif sama sekali.
--
-- password_hash disimpan sebagai hash (password_hash()), bukan plaintext,
-- persis seperti kolom users.password_hash.

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
-- email_verifications.pending_name
-- -------------------------------------------------------------------------

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verifications' AND COLUMN_NAME = 'pending_name') = 0,
  'ALTER TABLE email_verifications ADD COLUMN pending_name VARCHAR(100) NULL AFTER otp_hash',
  'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

-- -------------------------------------------------------------------------
-- email_verifications.pending_password_hash
-- -------------------------------------------------------------------------

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verifications' AND COLUMN_NAME = 'pending_password_hash') = 0,
  'ALTER TABLE email_verifications ADD COLUMN pending_password_hash VARCHAR(255) NULL AFTER pending_name',
  'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

-- -------------------------------------------------------------------------
-- email_verifications.is_reactivation
-- -------------------------------------------------------------------------

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_verifications' AND COLUMN_NAME = 'is_reactivation') = 0,
  'ALTER TABLE email_verifications ADD COLUMN is_reactivation TINYINT(1) NOT NULL DEFAULT 0 AFTER pending_password_hash',
  'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

-- Baris OTP lama (dibuat sebelum migrasi ini) adalah verifikasi pendaftaran
-- biasa, bukan reaktivasi.
UPDATE email_verifications SET is_reactivation = 0 WHERE is_reactivation IS NULL;
