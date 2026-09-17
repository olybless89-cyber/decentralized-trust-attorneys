-- Decentralized Trust Attorneys - Migration v1 -> v2
-- Run this via phpMyAdmin on your EXISTING database (the one already live
-- at webotester.online/dta-demo/) instead of re-importing schema.sql, so
-- you keep your existing users and applications.
--
-- phpMyAdmin > select your database > SQL tab > paste this in > Go.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS street_address VARCHAR(255) DEFAULT NULL AFTER phone,
  ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT NULL AFTER street_address,
  ADD COLUMN IF NOT EXISTS country VARCHAR(100) DEFAULT NULL AFTER city,
  ADD COLUMN IF NOT EXISTS state_region VARCHAR(100) DEFAULT NULL AFTER country,
  ADD COLUMN IF NOT EXISTS ssn_last4 CHAR(4) DEFAULT NULL AFTER state_region,
  ADD COLUMN IF NOT EXISTS id_document_path VARCHAR(255) DEFAULT NULL AFTER ssn_last4,
  ADD COLUMN IF NOT EXISTS balance DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER id_document_path;

CREATE TABLE IF NOT EXISTS withdrawals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  method VARCHAR(50) NOT NULL DEFAULT 'crypto',
  wallet_address VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
  admin_notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_wd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Note: if your MySQL/MariaDB version doesn't support "ADD COLUMN IF NOT EXISTS"
-- (older MySQL 5.x), remove the "IF NOT EXISTS" from each line above and run
-- the ALTER TABLE once — it will simply error harmlessly on any column that
-- already exists, which you can then comment out and re-run.
