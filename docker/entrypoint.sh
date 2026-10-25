#!/usr/bin/env bash
# Container start sequence: wait for DB → migrate → cache → exec supervisord.
# Designed to be idempotent — running on a fresh tenant or an upgrading one both work.

set -euo pipefail

cd /var/www/html

# ---------------------------------------------------------------------------
# Sanity: an APP_KEY must exist before Laravel will boot.
# Coolify/AgilaCP injects this via env vars during provisioning.
# ---------------------------------------------------------------------------
if [ -z "${APP_KEY:-}" ]; then
    echo "[entrypoint] FATAL: APP_KEY env var not set. AgilaCP/Coolify must inject this." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Wait for the database to accept connections.
# Don't block forever — surface real connection problems instead of hanging.
# ---------------------------------------------------------------------------
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
WAIT_TIMEOUT=120

echo "[entrypoint] Waiting up to ${WAIT_TIMEOUT}s for ${DB_HOST}:${DB_PORT}..."
START=$(date +%s)
until mariadb -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME:-root}" -p"${DB_PASSWORD:-}" -e 'SELECT 1' >/dev/null 2>&1; do
    NOW=$(date +%s)
    if [ $((NOW - START)) -gt ${WAIT_TIMEOUT} ]; then
        echo "[entrypoint] FATAL: database never came up at ${DB_HOST}:${DB_PORT}" >&2
        exit 1
    fi
    sleep 2
done
echo "[entrypoint] Database is up."

# ---------------------------------------------------------------------------
# Storage symlink (Laravel needs public/storage → storage/app/public)
# --force in case an old broken link is there from a previous container
# ---------------------------------------------------------------------------
php artisan storage:link --force --quiet || true

# ---------------------------------------------------------------------------
# Migrations — always run, --force suppresses the prod confirmation prompt.
# Idempotent: only new migrations execute. Safe on every container restart.
# ---------------------------------------------------------------------------
echo "[entrypoint] Running migrations..."
php artisan migrate --force --no-interaction

# ---------------------------------------------------------------------------
# Seeders — only run on explicit opt-in (first-tenant-install scenario).
# AgilaCP sets VENTA_RUN_SEEDERS=true on the very first deployment,
# then never again (it gets removed from env after first boot).
# ---------------------------------------------------------------------------
if [ "${VENTA_RUN_SEEDERS:-false}" = "true" ]; then
    echo "[entrypoint] Running seeders (first install)..."
    php artisan db:seed --force --no-interaction
fi

# ---------------------------------------------------------------------------
# Production caches — recompute on every container start so config/code
# changes from the new image take effect immediately. Cheap (~1 second).
# ---------------------------------------------------------------------------
echo "[entrypoint] Building optimization caches..."
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet
php artisan event:cache --quiet

echo "[entrypoint] Initialization complete. Handing off to: $*"

# ---------------------------------------------------------------------------
# Hand off to supervisord (or whatever CMD passed in)
# ---------------------------------------------------------------------------
exec "$@"
