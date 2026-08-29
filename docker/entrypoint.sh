#!/bin/bash
set -euo pipefail

cd /var/www/html

PORT="${PORT:-8080}"

# Cloud Run sets PORT (usually 8080). Bind Apache to 0.0.0.0:$PORT.
sed -i "s/^Listen .*/Listen 0.0.0.0:${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:.*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

DB_CONNECTION="${DB_CONNECTION:-sqlite}"

# SQLite only: ensure the DB file exists on the container filesystem.
# PostgreSQL (Neon) uses DB_* env vars — do not create a local sqlite file.
# Schema migrate / dictionary:import are NOT run here; apply them once against
# the target DB outside normal Cloud Run startup.
if [ "${DB_CONNECTION}" = "sqlite" ]; then
  if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
    chown www-data:www-data database/database.sqlite
  fi
fi

exec "$@"
