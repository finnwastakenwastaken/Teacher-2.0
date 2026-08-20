#!/bin/bash
#
# Creates the database the test suite runs against.
#
# Runs once, when the PostgreSQL data volume is first initialised. Tests use
# real PostgreSQL rather than SQLite because the schema depends on tsvector,
# GIN indexes and CHECK constraints — see the comment in phpunit.xml.
#
# In production this leaves behind one empty, unused database. That is a
# deliberate trade for making `php artisan test` work in every environment
# with no setup step.

set -euo pipefail

psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    SELECT 'CREATE DATABASE ${POSTGRES_DB}_testing'
    WHERE NOT EXISTS (
        SELECT FROM pg_database WHERE datname = '${POSTGRES_DB}_testing'
    )\gexec
EOSQL

echo "[init] Test database ${POSTGRES_DB}_testing is ready."
