#!/bin/sh
#
# Container entrypoint. Runs on every start, so everything here must be
# idempotent — a restart is not a fresh install.

set -eu

log() { echo "[entrypoint] $*"; }

# ---------------------------------------------------------------------------
# Named volumes mounted over storage/ are created root-owned and empty, while
# PHP-FPM workers run as www-data. The result is a container that starts
# cleanly and then returns 500 on every request, because Laravel cannot write
# its own log — and so fails inside its own error handler, producing no log to
# diagnose it with.
#
# Only possible when the container runs as root (development). The production
# image already ships correct ownership and runs as www-data, where its
# volumes seed from the image with the right owner.
# ---------------------------------------------------------------------------
if [ "$(id -u)" = '0' ]; then
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
fi

# ---------------------------------------------------------------------------
# Development only: vendor/ lives in a named volume that starts out empty, so
# the very first `up` has no autoloader and nothing below would run. Production
# images ship vendor/ baked in and never reach this branch.
# ---------------------------------------------------------------------------
if [ "${APP_ENV:-production}" != "production" ] && [ ! -f vendor/autoload.php ]; then
    log "No vendor/autoload.php — installing PHP dependencies (first run only)."
    composer install --no-interaction --prefer-dist
fi

# ---------------------------------------------------------------------------
# Everything below boots the *application*: it belongs to starting the server,
# not to running a one-off command. `docker compose run --rm app php artisan …`
# would otherwise migrate the database and rebuild caches as a side effect of
# asking a question — and, worse, the APP_KEY guard below would refuse to run
# the very command that generates the key, which is what the guard tells you
# to go and do.
#
# `docker compose exec` bypasses the entrypoint entirely, so this only affects
# `run`. Compare against the image's CMD, not against an allow-list of
# commands.
# ---------------------------------------------------------------------------
if [ "${1:-php-fpm}" != 'php-fpm' ]; then
    exec "$@"
fi

if [ -z "${APP_KEY:-}" ]; then
    log "FATAL: APP_KEY is not set."
    log "Generate one with: docker compose run --rm app php artisan key:generate --show"
    log "then put it in .env as APP_KEY=base64:..."
    exit 1
fi

# ---------------------------------------------------------------------------
# Wait for PostgreSQL. Compose health checks usually cover this, but a database
# that is still replaying WAL can accept a TCP connection while refusing
# queries, so verify with an actual connection rather than a port probe.
# ---------------------------------------------------------------------------
log "Waiting for PostgreSQL at ${DB_HOST:-database}:${DB_PORT:-5432} ..."
attempt=0
until php -r '
    $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s",
        getenv("DB_HOST") ?: "database",
        getenv("DB_PORT") ?: "5432",
        getenv("DB_DATABASE"));
    new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
' 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
        log "FATAL: PostgreSQL did not become available after 2 minutes."
        exit 1
    fi
    sleep 2
done
log "PostgreSQL is ready."

# ---------------------------------------------------------------------------
# Schema. --force skips the interactive confirmation; migrations are additive
# and safe to re-run.
# ---------------------------------------------------------------------------
log "Running migrations ..."
php artisan migrate --force --no-interaction

# ---------------------------------------------------------------------------
# Pre-seed the admin account from ADMIN_* env vars, if configured. A no-op on
# every boot after the first — see App\Console\Commands\SeedAdminFromEnvironment
# and App\Support\AdminAccount for why this is safe to call unconditionally.
# ---------------------------------------------------------------------------
php artisan admin:seed --no-interaction

# ---------------------------------------------------------------------------
# Starting data. Today that is only the education levels, which are seeded
# rather than hardcoded so the owner can rename and reorder them. Every
# seeder here must be idempotent for the same reason admin:seed is: this runs
# on every boot, not just the first, and must never resurrect a level the
# owner deliberately deleted. See Database\Seeders\DatabaseSeeder.
# ---------------------------------------------------------------------------
php artisan db:seed --force --no-interaction

# ---------------------------------------------------------------------------
# The icon catalogue: geometry for every icon the owner can choose, generated
# from the icon packages by `npm run icons:build` and committed as
# database/data/icons.json. Loading it here rather than in a seeder keeps it
# out of `db:seed`, which is about starting *content*; this is derived data
# that a new image can legitimately replace wholesale. A checksum makes the
# common case — nothing changed — a no-op.
# ---------------------------------------------------------------------------
php artisan icons:sync --no-interaction

# ---------------------------------------------------------------------------
# Chunk data for uploads that were started and never finished. These sit on
# the media volume, which is what gets backed up, so an abandoned multi-
# gigabyte upload would otherwise persist into every backup from then on.
# A no-op when there is nothing stale.
# ---------------------------------------------------------------------------
php artisan media:prune-uploads --no-interaction

# ---------------------------------------------------------------------------
# Caches. Built at start rather than at image build time, because they bake in
# runtime environment values that are not known while building.
# ---------------------------------------------------------------------------
if [ "${APP_ENV:-production}" = "production" ]; then
    log "Caching configuration, routes and views ..."
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction
else
    log "Development environment — clearing caches instead of building them."
    php artisan config:clear --no-interaction
    php artisan route:clear --no-interaction
    php artisan view:clear --no-interaction
fi

log "Ready."
exec "$@"
