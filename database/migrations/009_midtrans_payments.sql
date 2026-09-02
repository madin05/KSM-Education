-- KSM Education - Midtrans Payment Gateway Integration
-- Target: MySQL 5.7+ / MariaDB 10.2+

SET NAMES utf8mb4;

-- Add Midtrans fields to token_purchase_requests if not exists
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'midtrans_order_id') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN midtrans_order_id VARCHAR(100) NULL AFTER public_id', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'snap_token') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN snap_token VARCHAR(255) NULL AFTER midtrans_order_id', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'payment_type') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN payment_type VARCHAR(50) NULL AFTER price_rupiah', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND INDEX_NAME = 'idx_token_purchase_midtrans_order') = 0,
  'ALTER TABLE token_purchase_requests ADD UNIQUE INDEX idx_token_purchase_midtrans_order (midtrans_order_id)', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
