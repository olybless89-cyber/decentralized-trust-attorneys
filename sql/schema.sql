-- DecTrust Attorney - Full Schema (v14, consolidated)
--
-- This file is a manual-import FALLBACK only. The app now creates all of
-- these tables automatically on first page load (see ensure_core_tables()
-- in db.php, ensure_investment_tables() / ensure_asset_balances_table() in
-- includes/wallet.php, and ensure_next_of_kin_columns() in auth.php) — so on
-- most hosts (Railway, Render, etc.) you don't need to run this file at all:
-- just point the app at an empty MySQL database and load any page.
--
-- Use this file instead only on a host where the database user isn't
-- allowed to run CREATE TABLE from the app (some locked-down cPanel/shared
-- hosts): import it once via phpMyAdmin (select your DB > Import), or paste
-- it into Railway's Data > Query tab one statement at a time.
--
-- This file supersedes sql/migration_v2.sql through sql/migration_v13.sql —
-- those are kept only as a historical record of how the schema evolved.
-- A brand-new database needs only this one file.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  street_address VARCHAR(255) DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  country VARCHAR(100) DEFAULT NULL,
  state_region VARCHAR(100) DEFAULT NULL,
  ssn_last4 CHAR(4) DEFAULT NULL,
  id_document_path VARCHAR(255) DEFAULT NULL,
  balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  wallet_address VARCHAR(64) DEFAULT NULL,
  linked_wallet_address VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS investment_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  duration_days INT NOT NULL,
  interest_rate_percent DECIMAL(6,2) NOT NULL,
  min_amount_usd DECIMAL(18,2) NOT NULL DEFAULT 100.00,
  max_amount_usd DECIMAL(18,2) DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
  is_popular TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  entity_type VARCHAR(30) NOT NULL,
  business_name VARCHAR(200) NOT NULL,
  state VARCHAR(100) NOT NULL,
  owner_name VARCHAR(150) NOT NULL,
  owner_email VARCHAR(150) NOT NULL,
  owner_phone VARCHAR(30) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  next_kin_name VARCHAR(150) DEFAULT NULL,
  next_kin_relationship VARCHAR(60) DEFAULT NULL,
  next_kin_phone VARCHAR(30) DEFAULT NULL,
  next_kin_email VARCHAR(150) DEFAULT NULL,
  next_kin_address VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','in_review','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wallet_connections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  address VARCHAR(255) NOT NULL,
  provider VARCHAR(60) DEFAULT NULL,
  label VARCHAR(100) DEFAULT NULL,
  contact_email VARCHAR(190) DEFAULT NULL,
  chain VARCHAR(20) NOT NULL DEFAULT 'EVM',
  network VARCHAR(30) NOT NULL DEFAULT 'mainnet',
  connection_method VARCHAR(20) NOT NULL DEFAULT 'manual',
  method VARCHAR(30) NOT NULL DEFAULT 'manual',
  status VARCHAR(20) NOT NULL DEFAULT 'success',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL,
  UNIQUE KEY uq_user_addr_chain (user_id, address, chain),
  CONSTRAINT fk_wc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asset_balances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  asset_symbol VARCHAR(20) NOT NULL,
  asset_name VARCHAR(100) NOT NULL DEFAULT '',
  crypto_amount DECIMAL(30,10) NOT NULL DEFAULT 0,
  demo_usd_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_asset (user_id, asset_symbol),
  CONSTRAINT fk_ab_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS investments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan_id INT NOT NULL,
  plan_name VARCHAR(100) NOT NULL,
  asset_symbol VARCHAR(20) NOT NULL,
  principal_usd DECIMAL(18,2) NOT NULL,
  locked_crypto_amount DECIMAL(30,10) NOT NULL DEFAULT 0,
  interest_rate_percent DECIMAL(6,2) NOT NULL,
  duration_days INT NOT NULL,
  interest_usd DECIMAL(18,2) NOT NULL,
  payout_usd DECIMAL(18,2) NOT NULL,
  status ENUM('active','claimed','cancelled') NOT NULL DEFAULT 'active',
  starts_at DATETIME NOT NULL,
  matures_at DATETIME NOT NULL,
  claimed_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type ENUM('send','receive','swap','buy','admin_credit','admin_debit','roi_lock','roi_payout') NOT NULL,
  asset VARCHAR(20) NOT NULL DEFAULT 'USD',
  amount_usd DECIMAL(18,2) NOT NULL,
  counter_asset VARCHAR(20) DEFAULT NULL,
  destination VARCHAR(255) DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  status ENUM('completed','pending','failed','rejected') NOT NULL DEFAULT 'completed',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS withdrawals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  asset VARCHAR(20) NOT NULL DEFAULT 'BTC',
  method VARCHAR(50) NOT NULL DEFAULT 'crypto',
  wallet_address VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
  admin_notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_wd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed demo admin login: username = admin   password = Admin@123
-- (change this password after importing, via a new bcrypt hash)
INSERT INTO admins (username, password_hash) VALUES
('admin', '$2b$10$I7JUnKAaOK9LseJt.zEUfOz3fDEfvtvfn1qoKUSXtGdPyLK4tvTPO')
ON DUPLICATE KEY UPDATE username = username;

-- Seed the 3 Crypto ROI plans, live and ready to show on the homepage.
-- Only run this block once, on a fresh import — investment_plans has no
-- unique key on name, so pasting this a second time would add duplicates.
-- (The app's own auto-heal, ensure_investment_tables() in includes/wallet.php,
-- checks COUNT(*) = 0 first and won't ever duplicate these.)
INSERT INTO investment_plans (name, description, duration_days, interest_rate_percent, min_amount_usd, max_amount_usd, status, is_popular, sort_order) VALUES
('Starter Lock', 'A low-commitment way to try Crypto ROI.', 365, 5.00, 100.00, 4999.00, 'active', 0, 1),
('Growth Lock', 'Our most popular plan — a balanced return for a full year.', 365, 8.00, 5000.00, 50000.00, 'active', 1, 2),
('Elite Lock', 'Maximum return for our largest holders.', 365, 15.00, 50000.00, NULL, 'active', 0, 3);
