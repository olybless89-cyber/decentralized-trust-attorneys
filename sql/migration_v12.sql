-- Migration v12: Next of Kin fields on the Business Formation Application
--
-- Note: the site also auto-adds these columns the first time application.php
-- or admin/application_view.php is opened (see ensure_next_of_kin_columns()
-- in auth.php), so this migration isn't strictly required — but running it
-- directly avoids relying on that fallback firing on the very first submit.
--
-- If you already have these columns (e.g. the auto-heal above already added
-- them), this will error with "Duplicate column name" — that just means it's
-- already applied, skip it. (Plain ADD COLUMN, not "IF NOT EXISTS" — that
-- variant needs MySQL 8.0.29+ and errors out on older MySQL/MariaDB,
-- including Railway's default MySQL image.)
--
-- Railway: MySQL plugin -> Data tab -> Query. (Or phpMyAdmin -> SQL tab.)

ALTER TABLE applications
  ADD COLUMN next_kin_name VARCHAR(150) DEFAULT NULL AFTER address,
  ADD COLUMN next_kin_relationship VARCHAR(60) DEFAULT NULL AFTER next_kin_name,
  ADD COLUMN next_kin_phone VARCHAR(30) DEFAULT NULL AFTER next_kin_relationship,
  ADD COLUMN next_kin_email VARCHAR(150) DEFAULT NULL AFTER next_kin_phone,
  ADD COLUMN next_kin_address VARCHAR(255) DEFAULT NULL AFTER next_kin_email;
