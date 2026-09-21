-- Migration v10: add an optional contact email to wallet_connections.
--
-- Captured on the "Link Wallet" manual-connect form so support can reach a
-- user about a specific linked wallet if needed. Never a credential of any
-- kind — just an email address.

ALTER TABLE wallet_connections
  ADD COLUMN contact_email VARCHAR(190) DEFAULT NULL AFTER label;
