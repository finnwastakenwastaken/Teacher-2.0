#!/usr/bin/env bash
#
# Put a backup archive back, into this site.
#
#   sudo ./restore.sh /pad/naar/teacher-backup-2026-08-20-134500.tar.gz
#
# The other half of `backup:run`, and how a site moves to a new machine: run
# install.sh on the new box, then this. The admin account comes back with the
# database, so there's no claim screen to race.
#
# Replaces the database and every uploaded file — deliberately no merge mode,
# since reconciling two sites' content needs decisions a script can't make. It
# takes a safety archive of what's here first, so restoring the wrong file is
# recoverable.
#
# Overrides:
#   TEACHER_DIR           where the site is installed (default: this directory)
#   TEACHER_SKIP_BACKUP   1 to skip the safety archive — only when you have one
#   TEACHER_ASSUME_YES    1 to accept every prompt. Required for unattended use

set -euo pipefail

readonly HEALTH_TIMEOUT=300

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly SCRIPT_DIR

TEACHER_DIR="${TEACHER_DIR:-$SCRIPT_DIR}"
TEACHER_SKIP_BACKUP="${TEACHER_SKIP_BACKUP:-0}"
TEACHER_ASSUME_YES="${TEACHER_ASSUME_YES:-0}"

ARCHIVE=''
ARCHIVE_IN_CONTAINER=''

if [[ -t 1 ]]; then
    C_BOLD=$'\033[1m'; C_DIM=$'\033[2m'; C_RED=$'\033[31m'
    C_GREEN=$'\033[32m'; C_YELLOW=$'\033[33m'; C_OFF=$'\033[0m'
else
    C_BOLD=''; C_DIM=''; C_RED=''; C_GREEN=''; C_YELLOW=''; C_OFF=''
fi

step() { printf '\n%s==>%s %s%s%s\n' "$C_GREEN" "$C_OFF" "$C_BOLD" "$*" "$C_OFF"; }
info() { printf '    %s\n' "$*"; }
note() { printf '    %s%s%s\n' "$C_DIM" "$*" "$C_OFF"; }
warn() { printf '%s !! %s%s\n' "$C_YELLOW" "$*" "$C_OFF" >&2; }
fail() { printf '%s !! %s%s\n' "$C_RED" "$*" "$C_OFF" >&2; exit 1; }

confirm() {
    local prompt=$1
    [[ "$TEACHER_ASSUME_YES" == '1' ]] && return 0
    local answer
    read -r -p "    ${prompt} [j/N] " answer </dev/tty || return 1
    [[ "$answer" =~ ^[jJyY]$ ]]
}

usage() {
    printf 'Usage: sudo ./restore.sh <archive.tar.gz>\n\n'
    printf '  The archive is a file made by "php artisan backup:run", or\n'
    printf '  downloaded from the Back-ups screen in the admin panel.\n\n'
    exit 1
}

preflight() {
    step 'Checking'

    [[ "$(id -u)" == '0' ]] || fail 'Run this as root, or with sudo.'
    [[ -n "$ARCHIVE" ]] || usage
    [[ -f "$ARCHIVE" ]] || fail "There is no file at ${ARCHIVE}."

    cd "$TEACHER_DIR"
    [[ -f compose.yaml ]] || fail "compose.yaml not found in ${TEACHER_DIR}."
    [[ -f .env ]] || fail "No .env in ${TEACHER_DIR}. Run install.sh first — a restore needs a working site to restore into."

    docker compose version >/dev/null 2>&1 || fail 'docker compose is not available.'

    info "Archive: ${ARCHIVE}"
    info "Site:    ${TEACHER_DIR}"
}

start_stack() {
    step 'Making sure the site is running'

    # The dump is --clean --if-exists and drops/recreates its own objects, but
    # the container has to be there to receive it.
    docker compose up -d
    info 'Containers are up'
}

wait_for_database() {
    local waited=0

    while ! docker compose exec -T database pg_isready -q >/dev/null 2>&1; do
        sleep 2
        waited=$((waited + 2))
        [[ $waited -ge 120 ]] && fail 'The database did not become ready.'
    done
}

safety_archive() {
    if [[ "$TEACHER_SKIP_BACKUP" == '1' ]]; then
        warn 'Skipping the safety backup, because TEACHER_SKIP_BACKUP=1.'
        return
    fi

    step 'Archiving what is here now, first'

    if docker compose exec -T app php artisan backup:run; then
        note 'If this restore turns out to be the wrong archive, that one'
        note 'is on the Back-ups screen and can be put back the same way.'
    else
        warn 'The safety backup failed.'
        confirm 'Carry on and restore anyway?' || fail 'Stopped.'
    fi
}

copy_in() {
    step 'Copying the archive into the container'

    local container
    container="$(docker compose ps -q app)"
    [[ -n "$container" ]] || fail 'The app container is not running.'

    ARCHIVE_IN_CONTAINER="/tmp/$(basename -- "$ARCHIVE")"

    docker cp -- "$ARCHIVE" "${container}:${ARCHIVE_IN_CONTAINER}"
    info "Copied to ${ARCHIVE_IN_CONTAINER}"
}

restore() {
    step 'Restoring'

    printf '\n'
    warn 'This replaces the database and every uploaded file on this site.'
    confirm 'Continue?' || fail 'Stopped. Nothing was changed.'

    # --force because this runs without a terminal; the confirmation above is
    # the one a person answered.
    docker compose exec -T app php artisan backup:restore "$ARCHIVE_IN_CONTAINER" --force \
        || fail 'The restore failed. The site may be half-restored — do not put it back online until a good archive has gone in.'

    # As root, deliberately: `docker cp` writes the file root-owned, /tmp
    # carries the sticky bit, and the production image runs as www-data, which
    # can't unlink it — leaving an archive holding every password hash on the
    # site. (Development runs as root throughout, so it never showed this.)
    docker compose exec -T -u root app rm -f -- "$ARCHIVE_IN_CONTAINER" \
        || warn "Could not remove ${ARCHIVE_IN_CONTAINER} from the app container. It holds every password hash on the site — delete it by hand."
}

restart() {
    step 'Restarting'

    # Config and route caches are built at boot in production and can hold
    # values the restored settings table has just replaced.
    docker compose restart app web
    info 'Restarted'
}

wait_for_health() {
    step 'Waiting for the site to answer'

    local waited=0

    while true; do
        if curl -fsS -o /dev/null --max-time 5 http://127.0.0.1:8080/ 2>/dev/null; then
            info "Healthy after ${waited}s"
            return
        fi

        sleep 5
        waited=$((waited + 5))

        if [[ $waited -ge $HEALTH_TIMEOUT ]]; then
            warn "No healthy response after ${HEALTH_TIMEOUT}s. Recent logs:"
            docker compose logs --tail=40 app >&2 || true
            fail 'The site did not come up.'
        fi
    done
}

summary() {
    printf '\n  %sTeruggezet.%s\n\n' "$C_BOLD" "$C_OFF"
    printf '    Log in met het account uit de back-up — het wachtwoord is dat\n'
    printf '    van de site waar de back-up vandaan komt.\n\n'
    printf '    Kwijt? Dan:\n'
    printf '      docker compose exec app php artisan admin:reset-password\n\n'
}

main() {
    ARCHIVE="${1:-}"
    [[ "$ARCHIVE" == '-h' || "$ARCHIVE" == '--help' ]] && usage

    preflight
    start_stack
    wait_for_database
    safety_archive
    copy_in
    restore
    restart
    wait_for_health
    summary
}

main "$@"
