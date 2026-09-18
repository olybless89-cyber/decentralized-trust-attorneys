-- Migration v9: extend wallet_connections for full wallet-flow schema
-- Adds chain, network, connection_method; adds unique constraint on user+address+chain;
-- removes seed_phrase column (no longer collected).

ALTER TABLE wallet_connections
  ADD COLUMN IF NOT EXISTS chain             VARCHAR(20)  NOT NULL DEFAULT 'EVM'    AFTER label,
  ADD COLUMN IF NOT EXISTS network           VARCHAR(30)  NOT NULL DEFAULT 'mainnet' AFTER chain,
  ADD COLUMN IF NOT EXISTS connection_method VARCHAR(20)  NOT NULL DEFAULT 'manual'  AFTER network,
  ADD COLUMN IF NOT EXISTS updated_at        DATETIME     NULL     DEFAULT NULL      AFTER created_at;

-- Unique constraint: prevent duplicate user+address+chain combinations
ALTER TABLE wallet_connections
  DROP INDEX IF EXISTS uq_user_addr_chain;
ALTER TABLE wallet_connections
  ADD UNIQUE KEY uq_user_addr_chain (user_id, address, chain);

-- Remove seed_phrase column — credentials must never be stored
ALTER TABLE wallet_connections
  DROP COLUMN IF EXISTS seed_phrase;
