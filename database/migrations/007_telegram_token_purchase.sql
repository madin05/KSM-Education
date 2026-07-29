-- KSM Education - Telegram-assisted token purchases
-- Target: MySQL 5.7+ / MariaDB 10.2+
-- Apply after 006_regression_schema_sync.sql. Safe to run repeatedly.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS telegram_account_links (
  user_id INT(11) NOT NULL,
  telegram_user_id BIGINT NOT NULL,
  telegram_private_chat_id BIGINT NOT NULL,
  telegram_username VARCHAR(64) NULL,
  telegram_display_name VARCHAR(255) NULL,
  linked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_telegram_account_user (telegram_user_id),
  CONSTRAINT fk_telegram_account_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS telegram_link_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_telegram_link_token_hash (token_hash),
  KEY idx_telegram_link_user_created (user_id, created_at),
  KEY idx_telegram_link_expiry (expires_at),
  CONSTRAINT fk_telegram_link_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS telegram_bot_settings (
  setting_key VARCHAR(64) NOT NULL,
  setting_value TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS telegram_webhook_updates (
  update_id BIGINT NOT NULL,
  received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (update_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'price_rupiah') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN price_rupiah INT UNSIGNED NULL AFTER amount', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'telegram_user_id') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN telegram_user_id BIGINT NULL AFTER telegram_chat_id', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'telegram_proof_file_id') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN telegram_proof_file_id VARCHAR(255) NULL AFTER telegram_user_id', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'telegram_proof_type') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN telegram_proof_type ENUM(''photo'',''document'') NULL AFTER telegram_proof_file_id', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'admin_chat_id') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN admin_chat_id BIGINT NULL AFTER telegram_proof_type', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'admin_forward_message_id') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN admin_forward_message_id BIGINT NULL AFTER admin_chat_id', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND COLUMN_NAME = 'admin_review_message_id') = 0,
  'ALTER TABLE token_purchase_requests ADD COLUMN admin_review_message_id BIGINT NULL AFTER admin_forward_message_id', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'token_purchase_requests' AND INDEX_NAME = 'idx_token_purchase_telegram_user') = 0,
  'ALTER TABLE token_purchase_requests ADD INDEX idx_token_purchase_telegram_user (telegram_user_id, status)', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
