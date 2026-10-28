# Database backups

Each file here (`db-backup-YYYYMMDD-HHMMSS.sql`) is a full dump — schema
**and** real data (users, applications, transactions, investments, wallet
connections, everything) — taken from the live Railway database by
`backup-db-to-repo.sh` in the repo root. The 20 most recent are kept.

## Taking a backup

From your Mac, inside the cloned repo:

```
./backup-db-to-repo.sh
```

See the comments at the top of that script for one-time setup (connection
details, installing `mysqldump`). Run it before any risky change, or on a
recurring schedule (cron/launchd) for ongoing protection.

## Restoring after a crash or data loss

1. Set up a fresh MySQL database on whatever host you're using (new Railway
   project, a different provider, wherever).
2. Pick the backup file you want to restore — usually the most recent one.
3. Import it. From a machine with `mysql` installed and network access to
   the new database:
   ```
   mysql --host=<new DB host> --port=<new DB port> --user=<user> --password \
     <database name> < backups/db-backup-<timestamp>.sql
   ```
   Or, if you only have Railway's web Data → Query tab (no local `mysql`
   client), open the backup file and paste it in — same one-statement-at-a-
   time limitation as `sql/schema.sql`.
4. Point the app's `MYSQLHOST` / `MYSQLPORT` / `MYSQLDATABASE` / `MYSQLUSER`
   / `MYSQLPASSWORD` variables at the restored database and reload the site.

Unlike `sql/schema.sql` (which only gives you an empty, freshly-seeded
site), restoring from one of these files brings back your actual users,
applications, balances and transaction history as of when the backup was
taken.
