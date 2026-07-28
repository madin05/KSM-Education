-- KSM Education - Phase 2 user submissions
-- Apply after 001_phase1_foundation.sql.

SET NAMES utf8mb4;

-- Upload ownership is required so a user cannot attach another user's PDF or
-- cover by guessing its numeric upload id. Legacy uploads remain nullable.
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uploads' AND COLUMN_NAME = 'user_id') = 0,
  'ALTER TABLE uploads ADD COLUMN user_id INT(11) NULL AFTER id', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'uploads' AND INDEX_NAME = 'idx_uploads_user_created') = 0,
  'ALTER TABLE uploads ADD INDEX idx_uploads_user_created (user_id, created_at)', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @upload_owner_fk := NULL;
SELECT k.CONSTRAINT_NAME INTO @upload_owner_fk
FROM information_schema.KEY_COLUMN_USAGE k
WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'uploads'
  AND k.COLUMN_NAME = 'user_id' AND k.REFERENCED_TABLE_NAME = 'users'
  AND k.REFERENCED_COLUMN_NAME = 'id' LIMIT 1;
SET @sql := IF(
  @upload_owner_fk IS NULL,
  'ALTER TABLE uploads ADD CONSTRAINT fk_uploads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;