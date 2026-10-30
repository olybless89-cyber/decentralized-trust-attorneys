# Deploying this app

The app is plain PHP + MySQL. It needs a web server that runs PHP and a
MySQL/MariaDB database — nothing else (no build step, no Node, no Composer
install required for it to run).

## Database setup (the only step that can go wrong)

The app creates its own database tables automatically the first time it
connects — see `sql/README.md` for details. You do **not** need to import
`sql/schema.sql` manually on a normal deploy. All you need to get right is
pointing the app at the right database.

The app reads these 5 environment variables (falls back to the `DB_*`
constants in `config.php` if they're not set — see "Non-Railway hosts"
below):

```
MYSQLHOST
MYSQLPORT
MYSQLDATABASE
MYSQLUSER
MYSQLPASSWORD
```

### On Railway

1. Add a **MySQL** database service to the project (not Postgres — this app
   only speaks MySQL).
2. On the **app service** (this repo) → **Variables** tab, set the 5
   variables above as **references** to the MySQL service, not literal
   values:
   ```
   MYSQLHOST     = ${{MySQL.MYSQLHOST}}
   MYSQLPORT     = ${{MySQL.MYSQLPORT}}
   MYSQLDATABASE = ${{MySQL.MYSQLDATABASE}}
   MYSQLUSER     = ${{MySQL.MYSQLUSER}}
   MYSQLPASSWORD = ${{MySQL.MYSQLPASSWORD}}
   ```
   Replace `MySQL` with whatever your MySQL service is actually named if you
   renamed it. Type `${{` in the value field and Railway should offer an
   autocomplete — picking the suggestion from that list is safer than typing
   the `${{...}}` syntax by hand.

   **This is the step that broke a previous deploy**: a variable got typed
   in as a literal value (at one point literally the app's own hostname)
   instead of a `${{...}}` reference to the MySQL service. That makes the
   app try to connect to itself and fail with "Connection refused" — the
   database service was healthy the whole time, the variable just pointed
   at the wrong place. If you ever see a database connection error on
   Railway, check this first: open the value (click the eye icon) and
   confirm it resolves to the **MySQL service's** hostname
   (`<service-name>.railway.internal`), not the app's own.
3. Deploy. Load any page — tables are created automatically on first
   request. Log in to `/admin/login.php` with `admin` / `Admin@123` and
   change that password immediately.

### Non-Railway hosts (cPanel, etc.)

If the `MYSQL*` environment variables aren't set at all, `config.php` falls
back to the `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` constants defined
near the top of that file — edit those directly for a host with no env var
support. Table auto-creation still works the same way; if the database
user doesn't have `CREATE TABLE` privileges, import `sql/schema.sql`
manually once instead (phpMyAdmin → your database → Import).

## Email (optional)

Set `SMTP_HOST` / `SMTP_PORT` / `SMTP_USER` / `SMTP_PASS` /
`SMTP_FROM` as variables if you want the app to send real emails
(signup confirmations, etc.). If `SMTP_HOST` is left empty, the app just
silently skips sending email — nothing breaks.

## Backups

See `backups/README.md` for taking and restoring a full data backup of a
live database, stored in this repo.
