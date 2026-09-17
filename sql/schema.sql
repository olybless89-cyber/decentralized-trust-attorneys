-- Decentralized Trust Attorneys - Demo Schema (v2)
-- Import this file via cPanel > phpMyAdmin (select your DB, then Import)
-- If you already imported v1 on a live site, use sql/migration_v2.sql instead
-- so you don't lose existing users/applications.

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
  status ENUM('pending','in_review','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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

CREATE TABLE IF NOT EXISTS wallet_connections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  address VARCHAR(255) NOT NULL,
  provider VARCHAR(60) DEFAULT NULL,
  label VARCHAR(100) DEFAULT NULL,
  method VARCHAR(30) NOT NULL DEFAULT 'manual',
  status VARCHAR(20) NOT NULL DEFAULT 'success',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed demo admin login: username = admin   password = Admin@123
-- (change this password after importing, via a new bcrypt hash)
INSERT INTO admins (username, password_hash) VALUES
('admin', '$2b$10$I7JUnKAaOK9LseJt.zEUfOz3fDEfvtvfn1qoKUSXtGdPyLK4tvTPO')
ON DUPLICATE KEY UPDATE username = username;
