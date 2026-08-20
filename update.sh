#!/usr/bin/env bash
#
# Update an installed site to the current version of the repository.
#
#   sudo ./update.sh
#
# Pulls, rebuilds, restarts. Migrations run from the container entrypoint, so
# they are part of the restart rather than a separate step.
#
# It takes a database dump first, because a migration is the only routine
# operation here that cannot simply be undone by starting the old containers
# again.
#
# Safe to re-run, and a no-op beyond a rebuild when there is nothing to pull.
#
# Overrides:
#   TEACHER_DIR           where the site is installed (default: this directory)
#   TEACHER_BACKUP_DIR    where to write the pre-update dump (default: /var/backups)
#   TEACHER_SKIP_BACKUP   1 to skip the dump — only when you have another one
#   TEACHER_SKIP_PULL     1 to rebuild the current checkout without pulling
#   TEACHER_ASSUME_YES    1 to accept every default without asking

set -euo pipefail

readonly HEALTH_TIMEOUT=300

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly SCRIPT_DIR

TEACHER_DIR="${TEACHER_DIR:-$SCRIPT_DIR}"
TEACHER_BACKUP_DIR="${TEACHER_BACKUP_DIR:-/var/backups}"
TEACHER_SKIP_BACKUP="${TEACHER_SKIP_BACKUP:-0}"
TEACHER_SKIP_PULL="${TEACHER_SKIP_PULL:-0}"
TEACHER_ASSUME_YES="${TEACHER_ASSUME_YES:-0}"

COMPOSE_PROFILE_ARGS=()

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

preflight() {
    step 'Checking'

    [[ "$(id -u)" == '0' ]] || fail 'Run this as root, or with sudo.'

    cd "$TEACHER_DIR"
    [[ -f compose.yaml ]] || fail "compose.yaml not found in ${TEACHER_DIR}."
    [[ -f .env ]] || fail "No .env in ${TEACHER_DIR}. This site has not been installed yet — run install.sh."

    docker compose version >/dev/null 2>&1 || fail 'docker compose is not available.'

    # Only start the tunnel if this installation uses one. Reading it back from
    # .env rather than asking again keeps a re-run from quietly changing the
    # shape of the running stack.
    if grep -qE '^CLOUDFLARE_TUNNEL_TOKEN=.+' .env; then
        COMPOSE_PROFILE_ARGS=(--profile tunnel)
        info 'This installation uses a Cloudflare Tunnel'
    else
        info 'No tunnel configured'
    fi

    info "Directory: ${TEACHER_DIR}"
}

backup_database() {
    step 'Backing up the database'

    if [[ "$TEACHER_SKIP_BACKUP" == '1' ]]; then
        warn 'TEACHER_SKIP_BACKUP=1 — no dump taken.'
        return
    fi

    if ! docker compose ps --status running --services 2>/dev/null | grep -qx database; then
        warn 'The database container is not running, so there is nothing to dump.'
        confirm 'Continue without a backup?' || fail 'Stopped.'
        return
    fi

    mkdir -p "$TEACHER_BACKUP_DIR"
    local target
    target="${TEACHER_BACKUP_DIR}/teacher-db-pre-update-$(date +%F-%H%M).sql.gz"

    # The credentials come from the container's own environment, so this keeps
    # working if the operator changes DB_USERNAME or DB_DATABASE, and no
    # password is ever passed as an argument.
    if docker compose exec -T database sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip >"$target"; then
        info "Wrote ${target} ($(du -h "$target" | cut -f1))"
        note 'This machine is not a backup. Copy it somewhere else.'
    else
        rm -f "$target"
        fail 'The dump failed. Not updating.'
    fi
}

pull_changes() {
    step 'Fetching the new version'

    if [[ "$TEACHER_SKIP_PULL" == '1' ]]; then
        note 'TEACHER_SKIP_PULL=1 — rebuilding the current checkout'
        return
    fi

    [[ -d .git ]] || { warn 'Not a git checkout — nothing to pull.'; return; }

    if [[ -n "$(git status --porcelain)" ]]; then
        warn 'There are local changes in this checkout:'
        git status --short >&2
        confirm 'Continue? A fast-forward pull will refuse to discard them.' || fail 'Stopped.'
    fi

    local before after
    before="$(git rev-parse --short HEAD)"
    git pull --ff-only
    after="$(git rev-parse --short HEAD)"

    if [[ "$before" == "$after" ]]; then
        info "Already at ${after} — nothing new."
    else
        info "${before} → ${after}"
        git --no-pager log --oneline "${before}..${after}" | head -20 | sed 's/^/      /'
    fi
}

rebuild_and_restart() {
    step 'Rebuilding and restarting'
    # Migrations, seeders and cache warming all happen in the entrypoint, so
    # bringing the containers up is the whole of the deploy.
    docker compose "${COMPOSE_PROFILE_ARGS[@]}" up -d --build

    step 'Waiting for the site to answer'
    local waited=0
    until curl -fsS --max-time 5 -o /dev/null http://127.0.0.1:8080/up; do
        (( waited += 5 ))
        if (( waited >= HEALTH_TIMEOUT )); then
            warn "No healthy response after ${HEALTH_TIMEOUT}s. Recent logs:"
            docker compose logs --tail=40 app >&2 || true
            fail 'The site did not come back up. The database dump from this run is in '"${TEACHER_BACKUP_DIR}"'.'
        fi
        sleep 5
    done
    info "Healthy after ${waited}s"
}

cleanup() {
    step 'Cleaning up old images'
    # Dangling layers only. `prune -a` would also remove images that are not
    # currently running but are still the way back to the previous version.
    docker image prune -f >/dev/null
    info 'Removed dangling images'
}

summary() {
    printf '\n%s%s%s\n\n' "$C_GREEN$C_BOLD" '  Bijgewerkt.' "$C_OFF"
    printf '    Controleer de site en haal één download op — dat is de snelste\n'
    printf '    controle dat zowel de applicatie als het uitserveren van\n'
    printf '    bestanden nog werkt.\n\n'
    printf '    Logboek:  docker compose logs --tail=40 app\n\n'
}

main() {
    printf '%s%s%s\n' "$C_BOLD" 'Teacher 2.0 — update' "$C_OFF"

    preflight
    backup_database
    pull_changes
    rebuild_and_restart
    cleanup
    summary
}

main "$@"
