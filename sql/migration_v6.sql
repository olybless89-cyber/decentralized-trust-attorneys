-- Migration v6: password reset tokens table + transactions ENUM fix
-- Run this after v5 on any existing install.

-- Password reset tokens for the Forgot Password flow.
CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fix transactions.status ENUM to include 'failed' (already in schema)
-- and also add 'rejected' so admin send-transaction rejections are stored cleanly
-- (admin/send-transactions.php now maps 'rejected' -> 'failed', but include both for safety).
-- Note: ALTER COLUMN ENUM is non-destructive — existing rows are untouched.
ALTER TABLE transactions
  MODIFY COLUMN status ENUM('completed','pending','failed','rejected') NOT NULL DEFAULT 'completed';
