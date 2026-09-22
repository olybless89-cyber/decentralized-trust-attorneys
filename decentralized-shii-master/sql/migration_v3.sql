-- Decentralized Trust Attorneys - Migration v2 -> v3
-- Adds the wallet features (Send / Receive / Swap / Buy) and their
-- transaction ledger. Run this on your EXISTING database instead of
-- re-importing schema.sql, so you keep your existing users/applications.
--
-- phpMyAdmin > select your database > SQL tab > paste this in > Go.
-- (Railway: MySQL plugin > Data tab > Query, or connect with any MySQL
-- client using the connection details on the plugin's Variables tab.)

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS wallet_address VARCHAR(64) DEFAULT NULL AFTER balance,
  ADD COLUMN IF NOT EXISTS linked_wallet_address VARCHAR(255) DEFAULT NULL AFTER wallet_address;

CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type ENUM('send','receive','swap','buy','admin_credit','admin_debit') NOT NULL,
  asset VARCHAR(20) NOT NULL DEFAULT 'USD',
  amount_usd DECIMAL(18,2) NOT NULL,
  counter_asset VARCHAR(20) DEFAULT NULL,
  destination VARCHAR(255) DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'completed',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Note: if your MySQL/MariaDB version doesn't support "ADD COLUMN IF NOT EXISTS"
-- (older MySQL 5.x), remove the "IF NOT EXISTS" and run the ALTER TABLE once —
-- it will simply error harmlessly if the column already exists.
