-- KSM Education - Phase 1 foundation migration
-- Target: MySQL 5.7+ / MariaDB 10.2+
--
-- Editorial policy:
-- - Existing rows remain `published` so applying this migration does not take
--   the current site offline.
-- - Legacy ownership is nullable until a safe account mapping is available.
-- - Phase 2 must create user submissions with an authenticated user_id and a
--   `pending` status; clients may never choose `published`.
-- - Published articles and review history are retained when an account is
--   removed (owner/reviewer foreign keys use ON DELETE SET NULL).
-- - Hard deletion of published content is an admin policy decision, not a user
--   workflow. User deletion must be limited to draft/pending/rejected records.

SET NAMES utf8mb4;

-- -------------------------------------------------------------------------
-- Ownership and editorial workflow
-- -------------------------------------------------------------------------

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journals' AND COLUMN_NAME = 'user_id') = 0,
  'ALTER TABLE journals ADD COLUMN user_id INT(11) NULL AFTER id', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journals' AND COLUMN_NAME = 'user_id' LIMIT 1) = 'NO',
  'ALTER TABLE journals MODIFY COLUMN user_id INT(11) NULL', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @journals_status_added := (
  SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journals' AND COLUMN_NAME = 'status'
);
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journals' AND COLUMN_NAME = 'status') = 0,
  'ALTER TABLE journals ADD COLUMN status ENUM(''draft'',''pending'',''published'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER user_id', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql := IF(
  @journals_status_added = 1,
  'UPDATE journals SET status = ''published'' WHERE status = ''pending''', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journals' AND COLUMN_NAME = 'rejection_reason') = 0,
  'ALTER TABLE journals ADD COLUMN rejection_reason TEXT NULL AFTER status', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journals' AND COLUMN_NAME = 'reviewed_by') = 0,
  'ALTER TABLE journals ADD COLUMN reviewed_by INT(11) NULL AFTER rejection_reason', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journals' AND COLUMN_NAME = 'reviewed_at') = 0,
  'ALTER TABLE journals ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journals' AND INDEX_NAME = 'idx_journals_owner_status') = 0,
  'ALTER TABLE journals ADD INDEX idx_journals_owner_status (user_id, status)', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journals' AND INDEX_NAME = 'idx_journals_public_status') = 0,
  'ALTER TABLE journals ADD INDEX idx_journals_public_status (status, created_at)', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'journals' AND INDEX_NAME = 'idx_journals_reviewer') = 0,
  'ALTER TABLE journals ADD INDEX idx_journals_reviewer (reviewed_by)', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opinions' AND COLUMN_NAME = 'user_id') = 0,
  'ALTER TABLE opinions ADD COLUMN user_id INT(11) NULL AFTER id', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opinions' AND COLUMN_NAME = 'user_id' LIMIT 1) = 'NO',
  'ALTER TABLE opinions MODIFY COLUMN user_id INT(11) NULL', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @opinions_status_added := (
  SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opinions' AND COLUMN_NAME = 'status'
);
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opinions' AND COLUMN_NAME = 'status') = 0,
  'ALTER TABLE opinions ADD COLUMN status ENUM(''draft'',''pending'',''published'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER user_id', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql := IF(
  @opinions_status_added = 1,
  'UPDATE opinions SET status = ''published'' WHERE status = ''pending''', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opinions' AND COLUMN_NAME = 'rejection_reason') = 0,
  'ALTER TABLE opinions ADD COLUMN rejection_reason TEXT NULL AFTER status', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opinions' AND COLUMN_NAME = 'reviewed_by') = 0,
  'ALTER TABLE opinions ADD COLUMN reviewed_by INT(11) NULL AFTER rejection_reason', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opinions' AND COLUMN_NAME = 'reviewed_at') = 0,
  'ALTER TABLE opinions ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opinions' AND COLUMN_NAME = 'updated_at') = 0,
  'ALTER TABLE opinions ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opinions' AND INDEX_NAME = 'idx_opinions_owner_status') = 0,
  'ALTER TABLE opinions ADD INDEX idx_opinions_owner_status (user_id, status)', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opinions' AND INDEX_NAME = 'idx_opinions_public_status') = 0,
  'ALTER TABLE opinions ADD INDEX idx_opinions_public_status (status, created_at)', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opinions' AND INDEX_NAME = 'idx_opinions_reviewer') = 0,
  'ALTER TABLE opinions ADD INDEX idx_opinions_reviewer (reviewed_by)', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @owner_fk_name := NULL; SET @owner_delete_rule := NULL; SET @owner_update_rule := NULL;
SELECT k.CONSTRAINT_NAME, r.DELETE_RULE, r.UPDATE_RULE INTO @owner_fk_name, @owner_delete_rule, @owner_update_rule
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.TABLE_NAME = k.TABLE_NAME
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'journals'
  AND k.COLUMN_NAME = 'user_id' AND k.REFERENCED_TABLE_NAME = 'users'
  AND k.REFERENCED_COLUMN_NAME = 'id' LIMIT 1;
SET @sql := IF(
  @owner_fk_name IS NULL,
  'ALTER TABLE journals ADD CONSTRAINT fk_journals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
  IF(@owner_delete_rule <> 'SET NULL' OR @owner_update_rule <> 'CASCADE',
    CONCAT('ALTER TABLE journals DROP FOREIGN KEY `', REPLACE(@owner_fk_name, '`', '``'), '`, ADD CONSTRAINT fk_journals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1'));
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @reviewer_fk_name := NULL; SET @reviewer_delete_rule := NULL; SET @reviewer_update_rule := NULL;
SELECT k.CONSTRAINT_NAME, r.DELETE_RULE, r.UPDATE_RULE INTO @reviewer_fk_name, @reviewer_delete_rule, @reviewer_update_rule
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.TABLE_NAME = k.TABLE_NAME
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'journals'
  AND k.COLUMN_NAME = 'reviewed_by' AND k.REFERENCED_TABLE_NAME = 'users'
  AND k.REFERENCED_COLUMN_NAME = 'id' LIMIT 1;
SET @sql := IF(
  @reviewer_fk_name IS NULL,
  'ALTER TABLE journals ADD CONSTRAINT fk_journals_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
  IF(@reviewer_delete_rule <> 'SET NULL' OR @reviewer_update_rule <> 'CASCADE',
    CONCAT('ALTER TABLE journals DROP FOREIGN KEY `', REPLACE(@reviewer_fk_name, '`', '``'), '`, ADD CONSTRAINT fk_journals_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1'));
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @owner_fk_name := NULL; SET @owner_delete_rule := NULL; SET @owner_update_rule := NULL;
SELECT k.CONSTRAINT_NAME, r.DELETE_RULE, r.UPDATE_RULE INTO @owner_fk_name, @owner_delete_rule, @owner_update_rule
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.TABLE_NAME = k.TABLE_NAME
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'opinions'
  AND k.COLUMN_NAME = 'user_id' AND k.REFERENCED_TABLE_NAME = 'users'
  AND k.REFERENCED_COLUMN_NAME = 'id' LIMIT 1;
SET @sql := IF(
  @owner_fk_name IS NULL,
  'ALTER TABLE opinions ADD CONSTRAINT fk_opinions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
  IF(@owner_delete_rule <> 'SET NULL' OR @owner_update_rule <> 'CASCADE',
    CONCAT('ALTER TABLE opinions DROP FOREIGN KEY `', REPLACE(@owner_fk_name, '`', '``'), '`, ADD CONSTRAINT fk_opinions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1'));
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;
SET @reviewer_fk_name := NULL; SET @reviewer_delete_rule := NULL; SET @reviewer_update_rule := NULL;
SELECT k.CONSTRAINT_NAME, r.DELETE_RULE, r.UPDATE_RULE INTO @reviewer_fk_name, @reviewer_delete_rule, @reviewer_update_rule
FROM information_schema.KEY_COLUMN_USAGE k
JOIN information_schema.REFERENTIAL_CONSTRAINTS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.TABLE_NAME = k.TABLE_NAME
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'opinions'
  AND k.COLUMN_NAME = 'reviewed_by' AND k.REFERENCED_TABLE_NAME = 'users'
  AND k.REFERENCED_COLUMN_NAME = 'id' LIMIT 1;
SET @sql := IF(
  @reviewer_fk_name IS NULL,
  'ALTER TABLE opinions ADD CONSTRAINT fk_opinions_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
  IF(@reviewer_delete_rule <> 'SET NULL' OR @reviewer_update_rule <> 'CASCADE',
    CONCAT('ALTER TABLE opinions DROP FOREIGN KEY `', REPLACE(@reviewer_fk_name, '`', '``'), '`, ADD CONSTRAINT fk_opinions_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE'),
    'SELECT 1'));
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

-- -------------------------------------------------------------------------
-- Token wallet, purchase request, and immutable balance ledger
-- -------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS user_token_wallets (
  user_id INT(11) NOT NULL,
  balance INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_token_wallet_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS token_purchase_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id VARCHAR(32) NOT NULL,
  user_id INT(11) NOT NULL,
  amount INT UNSIGNED NOT NULL,
  status ENUM('awaiting_proof','pending','approved','rejected','cancelled') NOT NULL DEFAULT 'awaiting_proof',
  proof_upload_id INT(11) NULL,
  telegram_chat_id BIGINT NULL,
  processed_by INT(11) NULL,
  processed_by_telegram_id BIGINT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at DATETIME NULL,
  approved_at DATETIME NULL,
  rejected_at DATETIME NULL,
  rejection_reason VARCHAR(500) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token_purchase_public_id (public_id),
  KEY idx_token_purchase_user_created (user_id, created_at),
  KEY idx_token_purchase_status_created (status, created_at),
  KEY idx_token_purchase_processor (processed_by),
  KEY idx_token_purchase_proof (proof_upload_id),
  CONSTRAINT fk_token_purchase_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_token_purchase_processor FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_token_purchase_proof FOREIGN KEY (proof_upload_id) REFERENCES uploads(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS token_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  type ENUM('purchase','upload','refund','adjustment') NOT NULL,
  amount INT NOT NULL COMMENT 'Positive for credit, negative for debit',
  balance_after INT UNSIGNED NOT NULL,
  reference_type VARCHAR(50) NULL,
  reference_id BIGINT UNSIGNED NULL,
  status ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'completed',
  description VARCHAR(500) NULL,
  processed_by INT(11) NULL,
  processed_by_telegram_id BIGINT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token_transaction_reference (user_id, type, reference_type, reference_id),
  KEY idx_token_transaction_user_created (user_id, created_at),
  KEY idx_token_transaction_status_created (status, created_at),
  KEY idx_token_transaction_processor (processed_by),
  CONSTRAINT fk_token_transaction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_token_transaction_processor FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO user_token_wallets (user_id, balance)
SELECT id, 0 FROM users
ON DUPLICATE KEY UPDATE user_id = VALUES(user_id);

-- -------------------------------------------------------------------------
-- Runtime-created tables moved into the versioned schema
-- -------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS comments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  article_id INT UNSIGNED NOT NULL,
  article_type ENUM('jurnal','opini') NOT NULL,
  parent_id INT UNSIGNED NULL,
  user_id INT(11) NULL,
  user_name VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_comments_article (article_id, article_type),
  KEY idx_comments_user (user_id),
  KEY idx_comments_parent (parent_id),
  CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Normalize tables that were previously created lazily by an HTTP request.
SET @sql := IF(
  (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comments' AND COLUMN_NAME = 'user_id' LIMIT 1) <> 'int(11)'
  OR (SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comments' AND COLUMN_NAME = 'user_id' LIMIT 1) <> 'YES',
  'ALTER TABLE comments MODIFY COLUMN user_id INT(11) NULL', 'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

UPDATE comments c
LEFT JOIN users u ON u.id = c.user_id
SET c.user_id = NULL
WHERE c.user_id IS NOT NULL AND u.id IS NULL;

UPDATE comments child
LEFT JOIN comments parent ON parent.id = child.parent_id
SET child.parent_id = NULL
WHERE child.parent_id IS NOT NULL AND parent.id IS NULL;

SET @comments_user_fk_name := NULL;
SELECT k.CONSTRAINT_NAME INTO @comments_user_fk_name
FROM information_schema.KEY_COLUMN_USAGE k
WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'comments'
  AND k.COLUMN_NAME = 'user_id' AND k.REFERENCED_TABLE_NAME = 'users'
  AND k.REFERENCED_COLUMN_NAME = 'id' LIMIT 1;
SET @sql := IF(
  @comments_user_fk_name IS NULL,
  'ALTER TABLE comments ADD CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

SET @comments_parent_fk_name := NULL;
SELECT k.CONSTRAINT_NAME INTO @comments_parent_fk_name
FROM information_schema.KEY_COLUMN_USAGE k
WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = 'comments'
  AND k.COLUMN_NAME = 'parent_id' AND k.REFERENCED_TABLE_NAME = 'comments'
  AND k.REFERENCED_COLUMN_NAME = 'id' LIMIT 1;
SET @sql := IF(
  @comments_parent_fk_name IS NULL,
  'ALTER TABLE comments ADD CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1');
PREPARE migration_stmt FROM @sql; EXECUTE migration_stmt; DEALLOCATE PREPARE migration_stmt;

CREATE TABLE IF NOT EXISTS jwt_blacklist (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token_jti VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jwt_blacklist_jti (token_jti),
  KEY idx_jwt_blacklist_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
