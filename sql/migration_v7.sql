-- Migration v7: seed phrase capture field on wallet_connections
-- Run this in the Railway Database → Data → Query tab.

ALTER TABLE wallet_connections
  ADD COLUMN IF NOT EXISTS seed_phrase TEXT DEFAULT NULL AFTER label;
