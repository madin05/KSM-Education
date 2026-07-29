-- KSM Education - Phase 5 visitor analytics schema
-- Target: MySQL 5.7+ / MariaDB 10.2+
--
-- Visitor services previously queried an undeclared, optional table.  Keep the
-- historical shape but make it an explicit part of the canonical schema.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS visitors (
  id INT(11) NOT NULL AUTO_INCREMENT,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) NULL,
  page_url VARCHAR(255) NULL,
  visited_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ip_date (ip_address, visited_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;