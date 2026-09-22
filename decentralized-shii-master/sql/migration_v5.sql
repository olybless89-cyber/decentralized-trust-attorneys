-- Decentralized Trust Attorneys - Migration v4 -> v5
-- Adds provider/label columns to wallet_connections and a status column
-- so a user can link wallets from a picker (Exodus, Coinbase, etc.) and
-- unlink them later, for the redesigned "Wallet Management" page.
-- Run this on your EXISTING database instead of re-importing schema.sql.
-- If you already have these two columns (e.g. you ran an earlier copy of
-- this file that used "ADD COLUMN IF NOT EXISTS"), this will error with
-- "Duplicate column name" — that just means it's already applied, skip it.
--
-- phpMyAdmin > select your database > SQL tab > paste this in > Go.
-- (Plain ADD COLUMN, not "IF NOT EXISTS" — that variant needs MySQL
-- 8.0.29+ and errors out on older MySQL/MariaDB versions, including
-- Railway's default MySQL image.)

ALTER TABLE wallet_connections
  ADD COLUMN provider VARCHAR(60) DEFAULT NULL AFTER address,
  ADD COLUMN label VARCHAR(100) DEFAULT NULL AFTER provider;

-- 'status' already exists (VARCHAR) — this just documents the new value
-- 'revoked' used when a user unlinks a wallet, alongside the existing
-- 'success' / 'failed'. No schema change needed for that.
