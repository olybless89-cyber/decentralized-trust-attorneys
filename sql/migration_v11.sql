-- Migration v11: Crypto ROI investment feature
-- Adds investment_plans (admin-configurable plans) and investments (each
-- user's active/matured/claimed positions), plus two new transaction types
-- so locks and payouts show correctly in "Recent Activity" on the dashboard.
--
-- Note: the site also auto-creates these two tables the first time invest.php
-- or the admin Crypto ROI pages are opened (see ensure_investment_tables()
-- in includes/wallet.php), so this migration isn't strictly required to make
-- the feature work — but run it anyway on a live install to also apply the
-- transactions ENUM update below (that one has no auto-heal, and without it
-- ROI locks/payouts still work but won't show in the activity feed).
--
-- Railway: MySQL plugin -> Data tab -> Query. (Or phpMyAdmin -> SQL tab.)

CREATE TABLE IF NOT EXISTS investment_plans (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  name                  VARCHAR(100)   NOT NULL,
  description           VARCHAR(255)   DEFAULT NULL,
  duration_days         INT            NOT NULL,
  interest_rate_percent DECIMAL(6,2)   NOT NULL,
  min_amount_usd        DECIMAL(18,2)  NOT NULL DEFAULT 100.00,
  max_amount_usd        DECIMAL(18,2)  DEFAULT NULL,
  status                ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
  sort_order            INT            NOT NULL DEFAULT 0,
  created_at            DATETIME       DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- plan_id is a soft reference (no FK) — each investment snapshots its own
-- plan_name/rate/duration at lock time, so a plan can be edited or deleted
-- later without touching past investments.
CREATE TABLE IF NOT EXISTS investments (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  user_id               INT            NOT NULL,
  plan_id               INT            NOT NULL,
  plan_name             VARCHAR(100)   NOT NULL,
  asset_symbol          VARCHAR(20)    NOT NULL,
  principal_usd         DECIMAL(18,2)  NOT NULL,
  locked_crypto_amount  DECIMAL(30,10) NOT NULL DEFAULT 0,
  interest_rate_percent DECIMAL(6,2)   NOT NULL,
  duration_days         INT            NOT NULL,
  interest_usd          DECIMAL(18,2)  NOT NULL,
  payout_usd            DECIMAL(18,2)  NOT NULL,
  status                ENUM('active','claimed','cancelled') NOT NULL DEFAULT 'active',
  starts_at             DATETIME       NOT NULL,
  matures_at            DATETIME       NOT NULL,
  claimed_at            DATETIME       DEFAULT NULL,
  created_at            DATETIME       DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed 3 recommended plans, left INACTIVE so nothing is user-visible until
-- the admin reviews the rates/terms in admin/investment-plans.php and
-- activates the ones they want live ("launch" = flip status to active).
-- Skipped automatically if plans already exist (e.g. auto-created by
-- ensure_investment_tables() on a prior page load).
INSERT INTO investment_plans (name, description, duration_days, interest_rate_percent, min_amount_usd, max_amount_usd, status, sort_order)
SELECT * FROM (SELECT
    'Starter Lock' AS name, 'A short, low-commitment way to try Crypto ROI.' AS description,
    30 AS duration_days, 8.00 AS interest_rate_percent, 100.00 AS min_amount_usd, 4999.00 AS max_amount_usd,
    'inactive' AS status, 1 AS sort_order
  UNION ALL SELECT
    'Growth Lock', 'Our most popular plan — a balanced term and rate.',
    90, 20.00, 500.00, 24999.00, 'inactive', 2
  UNION ALL SELECT
    'Elite Lock', 'Maximum return for longer-term holders.',
    180, 45.00, 2000.00, NULL, 'inactive', 3
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM investment_plans);

-- Add the two Crypto ROI transaction types so locks/payouts label correctly
-- in "Recent Activity" instead of erroring or falling back to empty.
ALTER TABLE transactions
  MODIFY COLUMN type ENUM('send','receive','swap','buy','admin_credit','admin_debit','roi_lock','roi_payout') NOT NULL;
