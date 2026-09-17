-- Decentralized Trust Attorneys - Migration v3 -> v4
-- Adds wallet connection logging and a coin/asset column on withdrawals,
-- to power the redesigned admin panel (Wallet Logs + Withdrawal Requests).
-- Run this on your EXISTING database instead of re-importing schema.sql.
--
-- phpMyAdmin > select your database > SQL tab > paste this in > Go.
-- (Railway: MySQL plugin > Connect tab for a ready-made `mysql` command,
-- or Data tab > Query.)

CREATE TABLE IF NOT EXISTS wallet_connections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  address VARCHAR(255) NOT NULL,
  method VARCHAR(30) NOT NULL DEFAULT 'manual',
  status VARCHAR(20) NOT NULL DEFAULT 'success',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill one log row per user who already has a linked wallet, so the
-- Wallet Logs page isn't empty right after upgrading.
INSERT INTO wallet_connections (user_id, address, method, status, created_at)
SELECT id, linked_wallet_address, 'manual', 'success', created_at
FROM users
WHERE linked_wallet_address IS NOT NULL AND linked_wallet_address <> ''
  AND id NOT IN (SELECT user_id FROM wallet_connections);

ALTER TABLE withdrawals
  ADD COLUMN IF NOT EXISTS asset VARCHAR(20) NOT NULL DEFAULT 'BTC' AFTER amount;

-- Note: on older MySQL/MariaDB without "ADD COLUMN IF NOT EXISTS", drop
-- "IF NOT EXISTS" and run once — it errors harmlessly if already applied.
