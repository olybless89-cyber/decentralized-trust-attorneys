-- Migration v8: per-asset balances
-- Run in Railway Database → Data → Query tab (or phpMyAdmin).

CREATE TABLE IF NOT EXISTS asset_balances (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT            NOT NULL,
  asset_symbol    VARCHAR(20)    NOT NULL,
  asset_name      VARCHAR(100)   NOT NULL DEFAULT '',
  crypto_amount   DECIMAL(30,10) NOT NULL DEFAULT 0,
  demo_usd_amount DECIMAL(18,2)  NOT NULL DEFAULT 0.00,
  created_at      DATETIME       DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_asset (user_id, asset_symbol),
  CONSTRAINT fk_ab_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
