#!/usr/bin/env bash
set -euo pipefail
if [ "${APP_ENV:-}" != "production" ]; then
  echo "ERROR: APP_ENV debe ser production en el entorno cloud" >&2
  exit 1
fi
if [ "${PORT:-80}" != "80" ]; then
  echo "ERROR: configura PORT=80 en Railway para el servidor Apache." >&2
  exit 1
fi
php /var/www/html/scripts/init_production.php
exec apache2-foreground
