#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Make sure mounted storage is usable.
if [ -d "storage" ]; then
    mkdir -p storage/app/private/books \
             storage/app/public \
             storage/framework/{cache,sessions,testing,views} \
             storage/logs \
             bootstrap/cache || true
    # Best-effort permissions; won't fail if read-only mount.
    chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
fi

exec "$@"
