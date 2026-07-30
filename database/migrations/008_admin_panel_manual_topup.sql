-- KSM Education - Admin panel manual top-up verification
-- Target: MySQL 5.7+ / MariaDB 10.2+
-- Apply after 007_telegram_token_purchase.sql. Safe to run repeatedly.
--
-- Alur Telegram mencatat pemutus keputusan sebagai id Telegram
-- (processed_by_telegram_id). Verifikasi manual dari admin/token_requests.php
-- dilakukan oleh admin internal, sehingga butuh kolom processed_by yang
-- menunjuk users(id) agar audit trail tetap utuh.

SET NAMES utf8mb4;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'processed_by') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN processed_by INT(11) NULL AFTER rejection_reason', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND INDEX_NAME = 'idx_token_purchase_processed_by') = 0,
  'ALTER TABLE token_purchase_requests ADD INDEX idx_token_purchase_processed_by (processed_by)', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests'
     AND CONSTRAINT_NAME = 'fk_token_purchase_processed_by') = 0,
  'ALTER TABLE token_purchase_requests ADD CONSTRAINT fk_token_purchase_processed_by
     FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

-- Ledger token_transactions juga mencatat admin internal pemroses top-up manual.
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_transactions' AND COLUMN_NAME = 'processed_by') = 0,
  'ALTER TABLE token_transactions ADD COLUMN processed_by INT(11) NULL AFTER status', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
