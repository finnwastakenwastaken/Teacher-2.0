#!/bin/bash
#
# Creates the database the test suite runs against (real PostgreSQL, not
# SQLite — the schema needs tsvector, GIN and CHECK constraints). Runs once,
# on first volume init; leaves one empty unused database in production, which
# is the trade for `php artisan test` working everywhere with no setup step.

set -euo pipefail

psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    SELECT 'CREATE DATABASE ${POSTGRES_DB}_testing'
    WHERE NOT EXISTS (
        SELECT FROM pg_database WHERE datname = '${POSTGRES_DB}_testing'
    )\gexec
EOSQL

echo "[init] Test database ${POSTGRES_DB}_testing is ready."
