#!/bin/bash
# Backs up the LIVE Railway database (full schema + real data) and pushes
# it into this repo under backups/, so if the database ever crashes or gets
# wiped, you can restore from the repo instead of losing everything.
#
# ONE-TIME SETUP:
#   1. cp .backup-env.example .backup-env
#   2. Open .backup-env and fill in the public MySQL connection details from
#      Railway: your project -> MySQL service -> "Connect" tab -> Public
#      Network. (.backup-env is git-ignored, so your password never gets
#      committed.)
#   3. Make sure you have the mysqldump command — on a Mac:
#        brew install mysql-client
#        echo 'export PATH="/opt/homebrew/opt/mysql-client/bin:$PATH"' >> ~/.zshrc
#        source ~/.zshrc
#
# USAGE: run this from inside your cloned repo folder.
#   ./backup-db-to-repo.sh
#
# Run it whenever you want a fresh backup — before a big change, or on a
# schedule (e.g. a weekly `cron`/`launchd` job on your Mac) for ongoing
# disaster-recovery coverage.

set -e

if [ ! -f .backup-env ]; then
  echo "Missing .backup-env — copy .backup-env.example to .backup-env and fill in your real Railway MySQL connection details first."
  exit 1
fi
source .backup-env

if ! command -v mysqldump >/dev/null 2>&1; then
  echo "mysqldump not found. On a Mac, install it with:"
  echo "  brew install mysql-client"
  echo "  echo 'export PATH=\"/opt/homebrew/opt/mysql-client/bin:\$PATH\"' >> ~/.zshrc && source ~/.zshrc"
  exit 1
fi

mkdir -p backups

STAMP=$(date +%Y%m%d-%H%M%S)
OUT="backups/db-backup-${STAMP}.sql"

echo "Backing up ${DB_NAME}@${DB_HOST}:${DB_PORT} -> ${OUT} ..."
mysqldump \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USER" \
  --password="$DB_PASS" \
  --single-transaction \
  --default-character-set=utf8mb4 \
  "$DB_NAME" > "$OUT"

echo "Backup written: $OUT ($(wc -l < "$OUT") lines)"

# Keep only the 20 most recent backups in the repo so it doesn't grow forever.
OLD_BACKUPS=$(ls -1t backups/db-backup-*.sql 2>/dev/null | tail -n +21)
if [ -n "$OLD_BACKUPS" ]; then
  echo "$OLD_BACKUPS" | while IFS= read -r f; do rm -- "$f"; done
fi

git add backups/
git commit -m "Database backup ${STAMP}"
git push origin main

echo "Done. Backup pushed to the repo."
