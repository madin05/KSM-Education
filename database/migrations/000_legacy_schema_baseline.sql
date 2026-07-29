-- KSM Education - Legacy schema baseline
-- Target: MySQL 5.7+ / MariaDB 10.2+
--
-- Migrations are the single source of truth for the database schema.  This
-- idempotent baseline defines the tables that predate Phase 1, so an empty
-- database can be built by applying every migration in filename order.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS uploads (
  id INT(11) NOT NULL AUTO_INCREMENT,
  filename VARCHAR(512) NOT NULL,
  original_name VARCHAR(512) NULL,
  mime VARCHAR(100) NULL,
  size INT(11) NULL,
  url VARCHAR(1024) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT(11) NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(200) NULL,
  role ENUM('admin','user') NULL DEFAULT 'user',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS drafts (
  id INT(11) NOT NULL AUTO_INCREMENT,
  user_email VARCHAR(255) NOT NULL,
  draft_type ENUM('journal','opinion') NOT NULL,
  draft_data LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_type (user_email, draft_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS journals (
  id INT(11) NOT NULL AUTO_INCREMENT,
  title VARCHAR(512) NOT NULL,
  abstract TEXT NULL,
  file_upload_id INT(11) NULL,
  cover_upload_id INT(11) NULL,
  authors LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  tags LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  pengurus TEXT NULL,
  email VARCHAR(255) NULL,
  contact VARCHAR(100) NULL,
  volume VARCHAR(100) NULL,
  views INT(11) NULL DEFAULT 0,
  client_temp_id VARCHAR(255) NULL,
  client_updated_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY file_upload_id (file_upload_id),
  KEY cover_upload_id (cover_upload_id),
  CONSTRAINT journals_ibfk_1 FOREIGN KEY (file_upload_id) REFERENCES uploads (id) ON DELETE SET NULL,
  CONSTRAINT journals_ibfk_2 FOREIGN KEY (cover_upload_id) REFERENCES uploads (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS opinions (
  id INT(11) NOT NULL AUTO_INCREMENT,
  title VARCHAR(512) NOT NULL,
  description TEXT NULL,
  category VARCHAR(50) NULL DEFAULT 'opini',
  author_name VARCHAR(255) NOT NULL DEFAULT 'Anonymous',
  file_upload_id INT(11) NULL,
  cover_upload_id INT(11) NULL,
  authors LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  tags LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  email VARCHAR(255) NULL,
  contact VARCHAR(100) NULL,
  client_temp_id VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  views INT(11) NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY file_upload_id (file_upload_id),
  KEY cover_upload_id (cover_upload_id),
  KEY idx_category (category),
  CONSTRAINT opinions_ibfk_1 FOREIGN KEY (file_upload_id) REFERENCES uploads (id) ON DELETE SET NULL,
  CONSTRAINT opinions_ibfk_2 FOREIGN KEY (cover_upload_id) REFERENCES uploads (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sync_queue (
  id INT(11) NOT NULL AUTO_INCREMENT,
  client_id VARCHAR(255) NULL,
  payload LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS user_preferences (
  id INT(11) NOT NULL AUTO_INCREMENT,
  user_email VARCHAR(255) NOT NULL,
  preference_key VARCHAR(100) NOT NULL,
  preference_value TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_user_pref (user_email, preference_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;