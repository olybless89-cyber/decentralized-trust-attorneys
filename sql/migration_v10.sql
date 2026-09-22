-- Migration v10: Add email and image_path columns to wallet_connections
-- Run this in your MySQL database / phpMyAdmin / Railway query console if needed.
-- Works on both standard MySQL and MariaDB.

-- Add `email` column (idempotent — safe to re-run)
SET @dbname = DATABASE();
SET @tablename = 'wallet_connections';
SET @columnname = 'email';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_schema = @dbname
       AND table_name   = @tablename
       AND column_name  = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(150) DEFAULT NULL AFTER label')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add `image_path` column (idempotent — safe to re-run)
SET @columnname = 'image_path';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_schema = @dbname
       AND table_name   = @tablename
       AND column_name  = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(255) DEFAULT NULL AFTER email')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
