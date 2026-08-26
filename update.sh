#!/usr/bin/env bash
#
# Update an installed site to the current version of the repository.
#
#   sudo ./update.sh
#
# Pulls, builds, migrates, restarts.
#
# The order is the whole design: build before anything is recreated, so a
# failed build leaves the old containers serving; then migrate from the *new*
# image against the still-running old one, because a migration can't be undone
# by restarting the old containers and should fail while the site is still up
# — not from inside an entrypoint that exits, restarts and fails again with
# the site answering 502 throughout.
#
# Safe to re-run, and a no-op beyond a rebuild when there is nothing to pull.
#
# Overrides:
#   TEACHER_DIR           where the site is installed (default: this directory)
#   TEACHER_BACKUP_DIR    where to write the pre-update dump (default: /var/backups)
#   TEACHER_KEEP_DUMPS    how many pre-update dumps to keep (default: 7)
#   TEACHER_SKIP_BACKUP   1 to skip the dump — only when you have another one
#   TEACHER_SKIP_PULL     1 to rebuild the current checkout without pulling
#   TEACHER_SMOKE         1 to run ./scripts/smoke-production.sh at the end
#   TEACHER_ASSUME_YES    1 to accept every default without asking

set -euo pipefail

readonly HEALTH_TIMEOUT=300
readonly REQUIRED_RAM_MB=3800
readonly REQUIRED_DISK_GB=10

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly SCRIPT_DIR

TEACHER_DIR="${TEACHER_DIR:-$SCRIPT_DIR}"
TEACHER_BACKUP_DIR="${TEACHER_BACKUP_DIR:-/var/backups}"
TEACHER_KEEP_DUMPS="${TEACHER_KEEP_DUMPS:-7}"
TEACHER_SKIP_BACKUP="${TEACHER_SKIP_BACKUP:-0}"
TEACHER_SKIP_PULL="${TEACHER_SKIP_PULL:-0}"
TEACHER_SMOKE="${TEACHER_SMOKE:-0}"
TEACHER_ASSUME_YES="${TEACHER_ASSUME_YES:-0}"

COMPOSE_PROFILE_ARGS=()

# Set once the dump exists, so no message ever points at a file that was
# never written — the timeout path used to name one after a skipped backup.
DUMP_PATH=''

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
    # `ja` and `yes` as well as `j` and `y`: the prompt is Dutch, it invites
    # the longer word, and reading that as "no" is a surprising way to stop.
    [[ "$answer" =~ ^([jJ](a|A)?|[yY](es|ES)?)$ ]]
}

# Printed wherever the update stops badly. This is a plain SQL dump, not a
# backup archive — `restore.sh` takes only a full archive (manifest, media and
# all) and refuses a bare SQL stream, so the recovery command has to be here.
restore_hint() {
    [[ -n "$DUMP_PATH" ]] || return 0

    printf '\n'
    note 'To put the database back as it was before this run:'
    printf '      docker compose stop app\n'
    printf '      gunzip -c %s \\\n' "$DUMP_PATH"
    printf "        | docker compose exec -T database sh -c 'psql -U \"\$POSTGRES_USER\" -d \"\$POSTGRES_DB\"'\n"
    printf '      docker compose start app\n\n'
    note 'This is a database dump, not a backup archive — restore.sh does not take it.'
}

preflight() {
    step 'Checking'

    [[ "$(id -u)" == '0' ]] || fail 'Run this as root, or with sudo.'

    cd "$TEACHER_DIR"
    [[ -f compose.yaml ]] || fail "compose.yaml not found in ${TEACHER_DIR}."
    [[ -f .env ]] || fail "No .env in ${TEACHER_DIR}. This site has not been installed yet — run install.sh."

    docker compose version >/dev/null 2>&1 || fail 'docker compose is not available.'

    # One update at a time — two overlapping (or one overlapping cron's
    # nightly backup:run) means a container destroyed under a command still
    # using it.
    if command -v flock >/dev/null 2>&1; then
        exec 9>"${TEACHER_DIR}/.update.lock"
        flock -n 9 || fail 'Another update is already running in this directory.'
    else
        note 'flock is not installed, so concurrent runs are not prevented'
    fi

    # The build competes with a running Postgres, PHP-FPM and nginx, so
    # headroom is worse than at install time.
    local ram_mb disk_gb
    ram_mb=$(awk '/^MemTotal:/ { printf "%d", $2 / 1024 }' /proc/meminfo)
    disk_gb=$(df -BG --output=avail "$TEACHER_DIR" | tail -1 | tr -dc '0-9')

    info "Memory: ${ram_mb} MB · free disk: ${disk_gb} GB"

    if (( ram_mb < REQUIRED_RAM_MB )); then
        warn 'The frontend is built on this machine and needs about 4 GB, alongside everything already running.'
        confirm 'Continue anyway?' || fail 'Stopped.'
    fi

    if (( disk_gb < REQUIRED_DISK_GB )); then
        warn "Less than ${REQUIRED_DISK_GB} GB free. A build needs several GB transiently, and this script writes a dump first."
        confirm 'Continue anyway?' || fail 'Stopped.'
    fi

    # Read back from .env rather than asking again, so a re-run can't quietly
    # change the shape of the running stack.
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

    # 0600 before a byte is written, not after: /var/backups is world-readable
    # on Debian and this dump holds every password hash and session. The umask
    # is set in a subshell so the rest of the script keeps the operator's own.
    # Credentials come from the container's own environment — never passed as
    # an argument — so this survives DB_USERNAME/DB_DATABASE changes too.
    if ( umask 077; docker compose exec -T database sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip >"$target" ); then
        DUMP_PATH="$target"
        info "Wrote ${target} ($(du -h "$target" | cut -f1))"
        note 'This machine is not a backup. Copy it somewhere else.'
    else
        rm -f "$target"
        fail 'The dump failed. Not updating.'
    fi

    prune_dumps
}

# Every run writes one dump, so without pruning they accumulate indefinitely.
# Same default and rule as BACKUP_KEEP: only ever prune this script's own
# dumps, matched by the name it gives them.
prune_dumps() {
    [[ "$TEACHER_KEEP_DUMPS" =~ ^[0-9]+$ ]] || return 0
    (( TEACHER_KEEP_DUMPS > 0 )) || return 0

    local -a old=()
    while IFS= read -r file; do
        old+=("$file")
    done < <(find "$TEACHER_BACKUP_DIR" -maxdepth 1 -type f \
                -name 'teacher-db-pre-update-*.sql.gz' -printf '%T@ %p\n' 2>/dev/null \
             | sort -rn \
             | tail -n "+$((TEACHER_KEEP_DUMPS + 1))" \
             | cut -d' ' -f2-)

    if (( ${#old[@]} > 0 )); then
        rm -f -- "${old[@]}"
        note "Removed ${#old[@]} pre-update dump(s), keeping the newest ${TEACHER_KEEP_DUMPS}"
    fi
}

pull_changes() {
    step 'Fetching the new version'

    if [[ "$TEACHER_SKIP_PULL" == '1' ]]; then
        note 'TEACHER_SKIP_PULL=1 — rebuilding the current checkout'
        return
    fi

    # An error, not a warning: a tarball or rsync'd checkout would otherwise
    # silently rebuild the same code and report "Bijgewerkt." forever.
    [[ -e .git ]] || fail 'This is not a git checkout, so there is nothing to pull. Set TEACHER_SKIP_PULL=1 to rebuild what is here.'

    # git refuses a repo owned by another user, and this script runs as root
    # while the clone may not have — set this first or the dirty-check below
    # silently returns nothing and the refusal surfaces as a raw error later.
    if ! git config --global --get-all safe.directory 2>/dev/null | grep -qxF "$TEACHER_DIR"; then
        git config --global --add safe.directory "$TEACHER_DIR"
    fi

    # `.update.lock` is excluded explicitly, not left to .gitignore. This
    # script creates it seconds earlier, and the .gitignore entry that hides it
    # only arrives with the version being pulled — so upgrading *from* a release
    # older than that entry stops to ask the operator about a file the script
    # itself just made, mid-update, with a prompt that defaults to no.
    local dirty
    dirty="$(git status --porcelain | grep -v '[[:space:]]\.update\.lock$' || true)"

    if [[ -n "$dirty" ]]; then
        warn 'There are local changes in this checkout:'
        printf '%s\n' "$dirty" >&2
        confirm 'Continue? A fast-forward pull will refuse to discard them.' || fail 'Stopped.'
    fi

    local before after
    before="$(git rev-parse --short HEAD)"

    # Wrapped because git's own error wording says nothing about the site's
    # state — which at this point is the reassuring part.
    git pull --ff-only || {
        warn 'Could not fast-forward. Nothing has been changed and the site is still running.'
        restore_hint
        fail 'Resolve the message above and run this again.'
    }

    after="$(git rev-parse --short HEAD)"

    if [[ "$before" == "$after" ]]; then
        info "Already at ${after} — nothing new."
    else
        info "${before} → ${after}"
        git --no-pager log --oneline "${before}..${after}" | head -20 | sed 's/^/      /'
    fi
}

# Keys install.sh writes that an older .env lacks. No general backfill
# mechanism exists: every other key has a config-file default, so a missing
# one just degrades gracefully. This one can't — config/fortify.php falls back
# to APP_KEY when unset, so rotating APP_KEY (supported, via
# APP_PREVIOUS_KEYS) would silently unenrol every passkey. Pinned to the
# current APP_KEY, as install.sh does, since that's the value existing
# passkey handles were derived from.
backfill_env() {
    step 'Checking .env for keys this version needs'

    local key='PASSKEYS_USER_HANDLE_SECRET'

    if grep -qE "^${key}=.+" .env; then
        info 'Nothing to add'
        return
    fi

    local app_key
    # Literal prefix match, not a regex — same rule as install.sh's set_env,
    # since this value is a generated secret. 9 is one past "APP_KEY=".
    app_key="$(awk 'index($0, "APP_KEY=") == 1 { print substr($0, 9); exit }' .env)"

    if [[ -z "$app_key" ]]; then
        warn "APP_KEY is empty, so ${key} cannot be pinned to it. Passkeys, if any are enrolled, will not survive an APP_KEY rotation."
        return
    fi

    KEY_NAME="$key" KEY_VALUE="$app_key" awk '
        BEGIN { key = ENVIRON["KEY_NAME"]; value = ENVIRON["KEY_VALUE"]; found = 0 }
        index($0, key "=") == 1 { print key "=" value; found = 1; next }
        { print }
        END { if (!found) print key "=" value }
    ' .env >.env.tmp
    mv .env.tmp .env
    chmod 600 .env

    info "Pinned ${key} so a future APP_KEY rotation cannot unenrol passkeys"
}

# Built before anything is recreated, so a failed build (usually an npm OOM on
# a small machine) leaves the site serving the old version.
#
# `--pull` and the `pull` beside it are what actually patch the containers:
# without them a rebuild reuses the cached base images forever (their apk
# layer never re-executes) and postgres/cloudflared are never re-pulled at
# all — a site can be a year behind on base images while `apt upgrade` reports
# everything current.
build_images() {
    step 'Pulling base images'

    docker compose "${COMPOSE_PROFILE_ARGS[@]}" pull --ignore-buildable 2>/dev/null \
        || docker compose "${COMPOSE_PROFILE_ARGS[@]}" pull --ignore-pull-failures 2>/dev/null \
        || note 'Could not refresh the pulled images; continuing with what is here.'

    step 'Tagging the current images as the way back'

    # `docker image prune -f` at the end removes untagged images, and after a
    # rebuild the *previous* images are exactly that — so without tagging them
    # first, the cleanup step would delete the only way back.
    local image
    for image in teacher-app teacher-web; do
        if docker image inspect "$image" >/dev/null 2>&1; then
            docker image tag "$image" "${image}:previous"
        fi
    done
    note 'teacher-app:previous and teacher-web:previous now point at what is running'

    step 'Building'
    docker compose "${COMPOSE_PROFILE_ARGS[@]}" build --pull
}

# Run from the new image against the still-running old stack. The entrypoint
# also migrates, so on a good day this makes that a no-op; on a bad day it's
# the point — a migration failing inside the entrypoint takes `set -eu` with
# it, the container exits, `restart: unless-stopped` retries it, and nginx
# answers 502 for as long as that goes on. Failing here instead stops the
# update with every container still serving.
migrate() {
    step 'Migrating the database'

    docker compose "${COMPOSE_PROFILE_ARGS[@]}" run --rm app php artisan migrate --force --no-interaction || {
        warn 'A migration failed. Nothing has been recreated — the site is still running the old version.'
        warn 'Postgres wraps each migration, but not the batch, so the schema may be part-way between versions.'
        restore_hint
        fail 'Stopped before touching the running containers.'
    }
}

restart() {
    step 'Restarting'
    docker compose "${COMPOSE_PROFILE_ARGS[@]}" up -d

    # nginx resolves `app` once at config load and caches it for the process's
    # life (no `resolver`, no upstream block — deliberate, since the name is
    # stable). That's only true if nginx starts *after* app: a PHP-only update
    # that leaves the built assets unchanged produces an unchanged `web` image,
    # which Compose then has no reason to recreate, so nginx keeps a dead
    # address and every request 502s while `logs app` looks perfectly healthy.
    # Cheap insurance, scoped with --no-deps so nothing else is recreated.
    docker compose "${COMPOSE_PROFILE_ARGS[@]}" up -d --force-recreate --no-deps web
}

verify() {
    step 'Waiting for the site to answer'

    local waited=0
    until curl -fsS --max-time 5 -o /dev/null http://127.0.0.1:8080/up; do
        (( waited += 5 ))
        if (( waited >= HEALTH_TIMEOUT )); then
            warn "No healthy response after ${HEALTH_TIMEOUT}s. Recent logs:"
            docker compose logs --tail=40 app >&2 || true
            restore_hint
            fail 'The site did not come back up.'
        fi
        sleep 5
    done
    info "Healthy after ${waited}s"

    # Loopback answering says nothing about internet reachability — a
    # restart-looping cloudflared would otherwise be a dark site reported as
    # a successful update.
    if (( ${#COMPOSE_PROFILE_ARGS[@]} > 0 )); then
        if docker compose "${COMPOSE_PROFILE_ARGS[@]}" ps --status running --services 2>/dev/null | grep -qx tunnel; then
            info 'The Cloudflare Tunnel is running'
        else
            warn 'The tunnel container is not running. The site answers locally but may be unreachable from outside.'
            warn 'Check it with: docker compose logs --tail=40 tunnel'
        fi
    fi

    # Opt-in: it answers questions only a production boot can (mount points
    # writable by the right user, most of all) but proves it with a real
    # backup archive — minutes of work and a large temp file on a site with
    # gigabytes of video. Its output is also English; this script is Dutch.
    if [[ "$TEACHER_SMOKE" == '1' && -x ./scripts/smoke-production.sh ]]; then
        step 'Running the production smoke test'
        ./scripts/smoke-production.sh http://127.0.0.1:8080 || warn 'The smoke test reported a problem. The site is up; read its output above.'
    fi
}

cleanup() {
    step 'Cleaning up old images'
    # Dangling layers only — the previous version is tagged `:previous` and
    # so exempt. `prune -a` would take that tag's images too.
    docker image prune -f >/dev/null
    info 'Removed dangling images'
}

summary() {
    printf '\n%s%s%s\n\n' "$C_GREEN$C_BOLD" '  Bijgewerkt.' "$C_OFF"
    printf '    Controleer de site en haal één download op — dat is de snelste\n'
    printf '    controle dat zowel de applicatie als het uitserveren van\n'
    printf '    bestanden nog werkt.\n\n'
    printf '    Logboek:        docker compose logs --tail=40 app\n'
    printf '    Uitgebreide controle (Engelstalig):\n'
    printf '                    sudo TEACHER_SMOKE=1 ./update.sh\n\n'
}

main() {
    printf '%s%s%s\n' "$C_BOLD" 'Teacher 2.0 — update' "$C_OFF"

    preflight
    backup_database
    pull_changes
    backfill_env
    build_images
    migrate
    restart
    verify
    cleanup
    summary
}

# `exit` on the same line as the call is not a flourish: this script replaces
# itself, since `git pull` rewrites update.sh while bash is still executing
# it. bash reads scripts incrementally by byte offset rather than buffering
# the whole file, so after `main` returns it could otherwise read the *new*
# file from the *old* offset and execute whatever text lands there. Both
# commands on one line means bash parses them together before running either,
# so `exit` is already in memory and the file is never read again.
#
# Nothing may be added below this line.
main "$@"; exit $?
