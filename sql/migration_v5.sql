-- Decentralized Trust Attorneys - Migration v4 -> v5
-- Adds provider/label columns to wallet_connections and a status column
-- so a user can link wallets from a picker (Exodus, Coinbase, etc.) and
-- unlink them later, for the redesigned "Wallet Management" page.
-- Run this on your EXISTING database instead of re-importing schema.sql.
--
-- phpMyAdmin > select your database > SQL tab > paste this in > Go.

ALTER TABLE wallet_connections
  ADD COLUMN IF NOT EXISTS provider VARCHAR(60) DEFAULT NULL AFTER address,
  ADD COLUMN IF NOT EXISTS label VARCHAR(100) DEFAULT NULL AFTER provider;

-- 'status' already exists (VARCHAR) — this just documents the new value
-- 'revoked' used when a user unlinks a wallet, alongside the existing
-- 'success' / 'failed'. No schema change needed for that.
