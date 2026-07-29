-- KSM Education - Critical/high regression schema synchronization
-- Target: MySQL 5.7+ / MariaDB 10.2+
-- Apply after 005. Safe to run repeatedly on both legacy and current schemas.

SET NAMES utf8mb4;

-- Use the canonical proof column consumed by the runtime services.
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'proof_file_id') = 0
  AND
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'proof_upload_id') = 1,
  'ALTER TABLE token_purchase_requests CHANGE COLUMN proof_upload_id proof_file_id INT(11) NULL', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'proof_file_id') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN proof_file_id INT(11) NULL AFTER status', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

-- Keep wallets available for users created before this synchronization runs.
INSERT INTO user_token_wallets (user_id, balance)
SELECT id, 0 FROM users
ON DUPLICATE KEY UPDATE user_id = VALUES(user_id);

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'approved_at') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN approved_at DATETIME NULL AFTER submitted_at', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'rejected_at') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN rejected_at DATETIME NULL AFTER approved_at', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_transactions' AND COLUMN_NAME = 'description') = 0,
  'ALTER TABLE token_transactions ADD COLUMN description VARCHAR(500) NULL AFTER status', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_transactions' AND COLUMN_NAME = 'processed_by_telegram_id') = 0,
  'ALTER TABLE token_transactions ADD COLUMN processed_by_telegram_id BIGINT NULL AFTER processed_by', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = 'admin_reply') = 0,
  'ALTER TABLE contact_messages ADD COLUMN admin_reply TEXT NULL AFTER status', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = 'replied_by') = 0,
  'ALTER TABLE contact_messages ADD COLUMN replied_by INT(11) NULL AFTER admin_reply', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = 'replied_at') = 0,
  'ALTER TABLE contact_messages ADD COLUMN replied_at DATETIME NULL AFTER replied_by', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = 'read_at') = 0,
  'ALTER TABLE contact_messages ADD COLUMN read_at DATETIME NULL AFTER replied_at', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = 'closed_at') = 0,
  'ALTER TABLE contact_messages ADD COLUMN closed_at DATETIME NULL AFTER read_at', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = 'updated_at') = 0,
  'ALTER TABLE contact_messages ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND INDEX_NAME = 'contact_messages_replier_fk') = 0,
  'ALTER TABLE contact_messages ADD KEY contact_messages_replier_fk (replied_by)', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

UPDATE contact_messages cm
LEFT JOIN users u ON u.id = cm.replied_by
SET cm.replied_by = NULL
WHERE cm.replied_by IS NOT NULL AND u.id IS NULL;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
   WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages'
     AND COLUMN_NAME = 'replied_by' AND REFERENCED_TABLE_NAME = 'users'
     AND REFERENCED_COLUMN_NAME = 'id') = 0,
  'ALTER TABLE contact_messages ADD CONSTRAINT contact_messages_replier_fk FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
