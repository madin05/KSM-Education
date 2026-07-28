-- KSM Education - Phase 3 account and preference migration
-- Target: MySQL 5.7+ / MariaDB 10.2+
-- This migration is intentionally separate from the Phase 1 and Phase 2
-- migrations.  It is idempotent and preserves existing article ownership.

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
-- Profile and account lifecycle fields
-- -------------------------------------------------------------------------

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'bio') = 0,
  'ALTER TABLE users ADD COLUMN bio VARCHAR(500) NULL AFTER name', 'SELECT 1');
PREPARE phase3_stmt FROM @sql; EXECUTE phase3_stmt; DEALLOCATE PREPARE phase3_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_url') = 0,
  'ALTER TABLE users ADD COLUMN avatar_url VARCHAR(1024) NULL AFTER bio', 'SELECT 1');
PREPARE phase3_stmt FROM @sql; EXECUTE phase3_stmt; DEALLOCATE PREPARE phase3_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'account_status') = 0,
  'ALTER TABLE users ADD COLUMN account_status ENUM(''active'',''disabled'',''deleted'') NOT NULL DEFAULT ''active'' AFTER role', 'SELECT 1');
PREPARE phase3_stmt FROM @sql; EXECUTE phase3_stmt; DEALLOCATE PREPARE phase3_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_changed_at') = 0,
  'ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER account_status', 'SELECT 1');
PREPARE phase3_stmt FROM @sql; EXECUTE phase3_stmt; DEALLOCATE PREPARE phase3_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'deleted_at') = 0,
  'ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL AFTER password_changed_at', 'SELECT 1');
PREPARE phase3_stmt FROM @sql; EXECUTE phase3_stmt; DEALLOCATE PREPARE phase3_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'updated_at') = 0,
  'ALTER TABLE users ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at', 'SELECT 1');
PREPARE phase3_stmt FROM @sql; EXECUTE phase3_stmt; DEALLOCATE PREPARE phase3_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_account_status') = 0,
  'ALTER TABLE users ADD INDEX idx_users_account_status (account_status)', 'SELECT 1');
PREPARE phase3_stmt FROM @sql; EXECUTE phase3_stmt; DEALLOCATE PREPARE phase3_stmt;

-- -------------------------------------------------------------------------
-- Preferences are keyed by authenticated user id.  user_email is retained
-- for backward compatibility with the original schema and old rows.
-- -------------------------------------------------------------------------

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_preferences' AND COLUMN_NAME = 'user_id') = 0,
  'ALTER TABLE user_preferences ADD COLUMN user_id INT(11) NULL AFTER id', 'SELECT 1');
PREPARE phase3_stmt FROM @sql; EXECUTE phase3_stmt; DEALLOCATE PREPARE phase3_stmt;

UPDATE user_preferences p
LEFT JOIN users u ON u.email = p.user_email
SET p.user_id = u.id
WHERE p.user_id IS NULL AND u.id IS NOT NULL;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_preferences' AND INDEX_NAME = 'uq_user_preference') = 0,
  'ALTER TABLE user_preferences ADD UNIQUE KEY uq_user_preference (user_id, preference_key)', 'SELECT 1');
PREPARE phase3_stmt FROM @sql; EXECUTE phase3_stmt; DEALLOCATE PREPARE phase3_stmt;

SET @preference_fk_name := NULL; SET @preference_delete_rule := NULL;
SELECT k.CONSTRAINT_NAME, r.DELETE_RULE INTO @preference_fk_name, @preference_delete_rule
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.TABLE_NAME = k.TABLE_NAME
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'user_preferences'
  AND k.COLUMN_NAME = 'user_id' AND k.REFERENCED_TABLE_NAME = 'users'
  AND k.REFERENCED_COLUMN_NAME = 'id' LIMIT 1;
SET @sql := IF(
  @preference_fk_name IS NULL,
  'ALTER TABLE user_preferences ADD CONSTRAINT user_preferences_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE',
  IF(@preference_delete_rule <> 'CASCADE',
    CONCAT('ALTER TABLE user_preferences DROP FOREIGN KEY `', REPLACE(@preference_fk_name, '`', '``'), '`, ADD CONSTRAINT user_preferences_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE'),
    'SELECT 1'));
PREPARE phase3_stmt FROM @sql; EXECUTE phase3_stmt; DEALLOCATE PREPARE phase3_stmt;