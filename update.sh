#!/usr/bin/env bash
#
# Update an installed site to the current version of the repository.
#
#   sudo ./update.sh
#
# Pulls, builds, migrates, restarts.
#
# The order matters and is the whole design of this script. Building comes
# before anything is recreated, so a failed build leaves the old containers
# serving. Migrating comes before that too, run from the *new* image against
# the still-running old one, because a migration is the only step here that
# cannot be undone by starting the old containers again — and if it is going
# to fail, it should fail while the site is still up rather than from inside
# an entrypoint that then exits, restarts, and fails again with the site
# answering 502 throughout.
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

# What to do with the dump this script writes. Printed wherever the update
# stops badly, because the dump is only useful to someone holding the command
# that reads it — `restore.sh` takes a full backup archive (manifest, media
# and all) and refuses a bare SQL stream, which is exactly the moment nobody
# wants to discover that.
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

    # One update at a time. Two of these overlapping — or one overlapping the
    # nightly backup:run from cron — means a container being destroyed under
    # a command that is still using it.
    if command -v flock >/dev/null 2>&1; then
        exec 9>"${TEACHER_DIR}/.update.lock"
        flock -n 9 || fail 'Another update is already running in this directory.'
    else
        note 'flock is not installed, so concurrent runs are not prevented'
    fi

    # The build runs on this machine, and at update time it competes with a
    # running Postgres, PHP-FPM and nginx — so the headroom is strictly worse
    # than it was at install time, when this was last checked.
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

    # 0600 before a byte is written, not after: this holds the admin's
    # password hash, every access-password hash and every session, and
    # /var/backups is world-readable on Debian. The umask is set in a
    # subshell so the rest of the script keeps the operator's own.
    #
    # The credentials come from the container's own environment, so this keeps
    # working if the operator changes DB_USERNAME or DB_DATABASE, and no
    # password is ever passed as an argument.
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

# Every run writes one of these and nothing ever removed them, so a site
# updated monthly for two years kept twenty-four full database dumps on the
# same disk the media lives on. Same default as BACKUP_KEEP, and the same
# rule: never prune what was not asked for — only this script's own dumps,
# matched by the name this script gives them.
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

    # An error rather than a warning. A site unpacked from a tarball or
    # rsync'd from an old machine would otherwise rebuild the same code and
    # report "Bijgewerkt." forever, and the operator would believe they were
    # current. TEACHER_SKIP_PULL is how you say you meant it.
    [[ -e .git ]] || fail 'This is not a git checkout, so there is nothing to pull. Set TEACHER_SKIP_PULL=1 to rebuild what is here.'

    # git refuses to work in a repository owned by another user, and this
    # script runs as root while the clone may not have. That refusal arrives
    # as a raw error two commands later, after the dirty-check has silently
    # returned nothing.
    if ! git config --global --get-all safe.directory 2>/dev/null | grep -qxF "$TEACHER_DIR"; then
        git config --global --add safe.directory "$TEACHER_DIR"
    fi

    if [[ -n "$(git status --porcelain)" ]]; then
        warn 'There are local changes in this checkout:'
        git status --short >&2
        confirm 'Continue? A fast-forward pull will refuse to discard them.' || fail 'Stopped.'
    fi

    local before after
    before="$(git rev-parse --short HEAD)"

    # Wrapped, because git's own wording here ("Not possible to fast-forward",
    # "dubious ownership") says nothing about what state the site is in — and
    # at this point the answer is a reassuring one.
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

# Keys install.sh writes that an .env from an earlier version does not have.
#
# There is no general mechanism for this and there deliberately is not one:
# every other key the application reads has a default in its config file, so a
# new one arriving in an update degrades to that default rather than to null.
# This one cannot, because its fallback is a *plausible* value —
# config/fortify.php uses APP_KEY when it is unset, so rotating APP_KEY (a
# supported operation; APP_PREVIOUS_KEYS exists for it) silently unenrols
# every passkey with no way back.
#
# Pinned to the current APP_KEY rather than to something random, exactly as
# install.sh does for an existing site: that is the value the handles were
# derived from, so it keeps the enrolments that already exist working.
backfill_env() {
    step 'Checking .env for keys this version needs'

    local key='PASSKEYS_USER_HANDLE_SECRET'

    if grep -qE "^${key}=.+" .env; then
        info 'Nothing to add'
        return
    fi

    local app_key
    # A literal prefix match, not a regular expression: the value is a
    # generated secret and must never be re-interpreted. Same rule as
    # install.sh's set_env, and 9 is one past "APP_KEY=".
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

# Built before anything is recreated, so a build that fails — an npm OOM on a
# small machine is the usual way — leaves the site serving the old version.
#
# `--pull` and the `pull` beside it are what actually patch the containers.
# Without them a rebuild reuses the cached php:8.4-fpm-alpine and
# nginx:1.29-alpine forever, the apk layer never re-executes, and postgres and
# cloudflared are never re-pulled at all — so a site can be a year behind on
# every base image while `apt upgrade` reports everything up to date. The
# maintenance guides say so now.
build_images() {
    step 'Pulling base images'

    docker compose "${COMPOSE_PROFILE_ARGS[@]}" pull --ignore-buildable 2>/dev/null \
        || docker compose "${COMPOSE_PROFILE_ARGS[@]}" pull --ignore-pull-failures 2>/dev/null \
        || note 'Could not refresh the pulled images; continuing with what is here.'

    step 'Tagging the current images as the way back'

    # `docker image prune -f` at the end removes untagged images, and after a
    # rebuild the *previous* ones are exactly that: their tags have moved and
    # the containers that referenced them have been recreated. So the step
    # that claimed to protect the way back was the only thing deleting it.
    # Tagging first is what makes the claim true.
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

# Run from the new image against the still-running old stack.
#
# The entrypoint also migrates, so on a good day this is what makes that a
# no-op. On a bad day it is the whole point: a migration that fails inside the
# entrypoint takes the container's `set -eu` with it, the container exits,
# `restart: unless-stopped` starts it again, it fails again — and nginx
# answers 502 to every visitor for as long as that goes on. Both maintenance
# guides used to say the site keeps running on the old version until a build
# succeeds, which is true of a *build* failure and was never true of this one.
#
# Failing here instead stops the update with every container still serving.
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

    # nginx resolves `app` once, when it loads its configuration, and caches
    # that address for the life of the process — there is no `resolver` and no
    # upstream block, deliberately, because in this stack the name is stable.
    # It is stable only if nginx starts *after* whatever it points at: an
    # update that changes PHP but leaves the built assets byte-identical
    # produces an unchanged `web` image, which Compose then has no reason to
    # recreate, and nginx would hold the address of a container that no longer
    # exists. The symptom is every request 502ing while `logs app` ends in a
    # perfectly healthy "[entrypoint] Ready."
    #
    # Cheap insurance, scoped with --no-deps so it recreates nothing else.
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

    # 127.0.0.1:8080 is loopback-only by design, so answering there says
    # nothing about whether anyone on the internet can reach the site. When a
    # tunnel is what carries the traffic, a restart-looping cloudflared is a
    # dark site reported as a successful update.
    if (( ${#COMPOSE_PROFILE_ARGS[@]} > 0 )); then
        if docker compose "${COMPOSE_PROFILE_ARGS[@]}" ps --status running --services 2>/dev/null | grep -qx tunnel; then
            info 'The Cloudflare Tunnel is running'
        else
            warn 'The tunnel container is not running. The site answers locally but may be unreachable from outside.'
            warn 'Check it with: docker compose logs --tail=40 tunnel'
        fi
    fi

    # Opt-in, not automatic. It asks the questions only a production boot can
    # answer — that every named-volume mount point is writable by the user
    # that has to write to it, most of all — but it writes a real backup
    # archive to prove it, which on a site with gigabytes of video is minutes
    # of work and a large temporary file on every update. Its output is also
    # English, and this script is Dutch for a reason.
    if [[ "$TEACHER_SMOKE" == '1' && -x ./scripts/smoke-production.sh ]]; then
        step 'Running the production smoke test'
        ./scripts/smoke-production.sh http://127.0.0.1:8080 || warn 'The smoke test reported a problem. The site is up; read its output above.'
    fi
}

cleanup() {
    step 'Cleaning up old images'
    # Dangling layers only, and the previous version is no longer among them:
    # it was tagged `:previous` before the build. `prune -a` would take that
    # tag's images too.
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

# `exit` on the same line as the call, and that is not a flourish.
#
# This script replaces itself: `git pull` rewrites update.sh while bash is
# still executing it. bash reads a script incrementally, remembering a byte
# offset rather than holding the whole file, so once `main` returns it can
# read the *new* file from the offset the *old* one had reached — near the end
# of one file and the middle of another — and execute whatever text begins
# there.
#
# It has not bitten yet, and the reason is not reassuring: the previous
# version of this script was small enough to be buffered whole before the pull
# replaced it. This one is twice that size. Both commands sit on one line, so
# bash parses them together before running either and `exit` is already in
# memory when `main` finishes; the file is never read again.
#
# Nothing may be added below this line.
main "$@"; exit $?
