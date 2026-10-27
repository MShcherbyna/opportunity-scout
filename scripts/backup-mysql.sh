#!/usr/bin/env bash
# Dumps the production MySQL database to a timestamped, gzipped file.
# Intended to run via host cron, from the project root:
#   0 3 * * * /path/to/threads-scraper/scripts/backup-mysql.sh >> /var/log/opportunity-scout-backup.log 2>&1
set -euo pipefail

cd "$(dirname "$0")/.."

# shellcheck disable=SC1091
source .env

BACKUP_DIR="backups"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
OUT_FILE="${BACKUP_DIR}/${DB_DATABASE}-${TIMESTAMP}.sql.gz"

mkdir -p "$BACKUP_DIR"

docker compose -f docker-compose.prod.yml exec -T mysql \
    mysqldump -uroot -p"${DB_PASSWORD}" "${DB_DATABASE}" | gzip > "$OUT_FILE"

find "$BACKUP_DIR" -name '*.sql.gz' -mtime +14 -delete

echo "Backup written to ${OUT_FILE}"
