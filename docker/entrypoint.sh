#!/bin/bash
set -euo pipefail

cd /var/www/html

PORT="${PORT:-8080}"

# Cloud Run sets PORT (usually 8080). Bind Apache to 0.0.0.0:$PORT.
sed -i "s/^Listen .*/Listen 0.0.0.0:${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:.*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Ensure SQLite file exists on ephemeral filesystem
if [ ! -f database/database.sqlite ]; then
  touch database/database.sqlite
  chown www-data:www-data database/database.sqlite
fi

# Apply pending migrations when runtime APP_KEY is configured
if [ -n "${APP_KEY:-}" ]; then
  php artisan migrate --force --no-interaction || true
fi

exec "$@"
