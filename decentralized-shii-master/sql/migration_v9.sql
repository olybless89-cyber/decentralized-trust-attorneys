-- Migration v9: remove the seed-phrase capture column added in migration_v7.
--
-- The "Recovery Phrase" tab on the Wallet Management page (and the
-- matching admin "Reveal" / CSV export) has been removed from the app code.
-- This migration wipes any values already captured in that column, then
-- drops the column itself, so the data no longer exists anywhere.
--
-- Run this against the live database as soon as possible if migration_v7
-- was ever applied there.

UPDATE wallet_connections SET seed_phrase = NULL WHERE seed_phrase IS NOT NULL;
ALTER TABLE wallet_connections DROP COLUMN seed_phrase;
