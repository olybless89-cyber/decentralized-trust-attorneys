-- Migration v13: finalize Crypto ROI plan figures and launch them
--
-- Updates the 3 recommended plans (seeded inactive by migration_v11.sql) to
-- their final terms and switches them live, and adds an is_popular flag so
-- "Most Popular" is an admin choice instead of being computed from the
-- highest rate.
--
-- Note: the site also auto-adds the is_popular column the next time any
-- Crypto ROI page loads (see ensure_investment_tables() in includes/wallet.php),
-- so this migration isn't strictly required for the column itself — but the
-- UPDATE below (final figures + switching the plans live) only happens here.
--
-- Railway: MySQL plugin -> Data tab -> Query. (Or phpMyAdmin -> SQL tab.)

ALTER TABLE investment_plans
  ADD COLUMN is_popular TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
-- If you already have this column (e.g. the auto-heal above already added
-- it), the line above errors with "Duplicate column name" — that just means
-- it's already applied, skip it and run the UPDATEs below on their own.

UPDATE investment_plans SET
  description = 'A low-commitment way to try Crypto ROI.',
  duration_days = 365,
  interest_rate_percent = 5.00,
  min_amount_usd = 100.00,
  max_amount_usd = 4999.00,
  status = 'active',
  is_popular = 0,
  sort_order = 1
WHERE name = 'Starter Lock';

UPDATE investment_plans SET
  description = 'Our most popular plan — a balanced return for a full year.',
  duration_days = 365,
  interest_rate_percent = 8.00,
  min_amount_usd = 5000.00,
  max_amount_usd = 50000.00,
  status = 'active',
  is_popular = 1,
  sort_order = 2
WHERE name = 'Growth Lock';

UPDATE investment_plans SET
  description = 'Maximum return for our largest holders.',
  duration_days = 365,
  interest_rate_percent = 15.00,
  min_amount_usd = 50000.00,
  max_amount_usd = NULL,
  status = 'active',
  is_popular = 0,
  sort_order = 3
WHERE name = 'Elite Lock';
