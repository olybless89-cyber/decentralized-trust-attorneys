# Database setup

**You almost never need to run anything in this folder manually.** As of the
current version, the app creates its own database tables automatically the
first time it connects — point it at any empty MySQL database (Railway,
Render, a cPanel MySQL database, anywhere) and load any page. No phpMyAdmin,
no copy-pasting SQL.

This is handled by:
- `ensure_core_tables()` in `db.php` — creates `users`, `admins`,
  `applications`, `wallet_connections`, `transactions`, `withdrawals`,
  `password_reset_tokens`, and seeds a default admin login
  (`admin` / `Admin@123` — change this immediately after first login).
- `ensure_investment_tables()` and `ensure_asset_balances_table()` in
  `includes/wallet.php` — create `investment_plans`, `investments`, and
  `asset_balances`.
- `ensure_next_of_kin_columns()` in `auth.php` — keeps `applications`
  up to date with the Next of Kin fields on older databases.

All of these use `CREATE TABLE IF NOT EXISTS` and are safe to run on every
request — they do nothing once the tables already exist.

## schema.sql — manual fallback only

`schema.sql` is the complete, current schema (all 10 tables) for the rare
case where the above can't run — e.g. a locked-down host where the database
user isn't allowed `CREATE TABLE`. Import it once via phpMyAdmin, or paste it
into Railway's **Data → Query** tab one statement at a time (Railway only
runs one statement per paste).

It also seeds the 3 Crypto ROI plans as **active** (matching what's live on
the production site) and the default admin login. Only run it once, against
a fresh database — the `investment_plans` seed has no unique key to guard
against being inserted twice.

## migration_v2.sql – migration_v13.sql

Historical record of how the schema evolved over time. **`schema.sql` already
includes everything from all of these** — a new database needs only
`schema.sql` (or, usually, nothing at all). These files are kept for
reference only; you shouldn't need to run them again.

## Restoring from a backup

See `backups/README.md` for how to restore real data (not just the empty
schema) from a backup taken with `backup-db-to-repo.sh`.
