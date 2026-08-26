#!/usr/bin/env bash
#
# Smoke-test a running production stack.
#
#   ./scripts/smoke-production.sh [base-url]
#
#   SMOKE_PROJECT=teacher     compose project name to inspect
#   SMOKE_MEDIA_URL=/downloads/…   probe this gated file instead of discovering one
#
# Neither test suite can see this class of bug — both run the development
# target (root, bind mount), while production runs as www-data from a baked
# image with named volumes. That's exactly where things break: a directory
# missing from the image becomes an unwritable root-owned mount point, a file
# mode fine for the owner is unreadable by nginx, opcache serves whatever it
# loaded first. This asks those questions against a real production boot, over
# HTTP and `docker exec`, and asserts rather than prints. Run it after
# touching disk config, the web image, the entrypoint, or a volume mount
# point.
#
# Safe against a live site: writes one backup archive and deletes it again,
# otherwise only reads.

set -uo pipefail

readonly BASE_URL="${1:-http://127.0.0.1:8080}"
readonly PROJECT="${SMOKE_PROJECT:-teacher}"

# Named-volume mount points from compose.yaml: one missing from the image gets
# created root-owned by Docker, and the container doesn't run as root.
readonly WRITABLE_PATHS=(
    storage/app/private
    storage/app/backups
    storage/logs
    storage/framework/cache
    storage/framework/sessions
    storage/framework/views
    bootstrap/cache
)

# Every location in the nginx config that sets an add_header of its own must
# repeat the whole set, because add_header does not merge — a location with
# one discards every inherited one, silently.
readonly SECURITY_HEADERS=(
    x-content-type-options
    x-frame-options
    referrer-policy
    permissions-policy
    content-security-policy
)

if [[ -t 1 ]]; then
    C_BOLD=$'\033[1m'; C_DIM=$'\033[2m'; C_RED=$'\033[31m'
    C_GREEN=$'\033[32m'; C_YELLOW=$'\033[33m'; C_OFF=$'\033[0m'
else
    C_BOLD=''; C_DIM=''; C_RED=''; C_GREEN=''; C_YELLOW=''; C_OFF=''
fi

FAILURES=0
SKIPS=0

step() { printf '\n%s==>%s %s%s%s\n' "$C_GREEN" "$C_OFF" "$C_BOLD" "$*" "$C_OFF"; }
note() { printf '    %s%s%s\n' "$C_DIM" "$*" "$C_OFF"; }
pass() { printf '    %sok%s   %s\n' "$C_GREEN" "$C_OFF" "$*"; }
skip() { printf '    %sskip%s %s\n' "$C_YELLOW" "$C_OFF" "$*"; SKIPS=$((SKIPS + 1)); }
bad() { printf '    %sFAIL%s %s\n' "$C_RED" "$C_OFF" "$*"; FAILURES=$((FAILURES + 1)); }
die() { printf '%s !! %s%s\n' "$C_RED" "$*" "$C_OFF" >&2; exit 2; }

check() {
    local label="$1" expected="$2" actual="$3"

    if [[ "$actual" == "$expected" ]]; then
        pass "$label"
    else
        bad "$label — expected ${expected}, got ${actual}"
    fi
}

# Found by label rather than by `compose ps`, so this runs from any directory
# and needs none of the compose files to be present or to match.
container_for() {
    docker ps --quiet \
        --filter "label=com.docker.compose.project=${PROJECT}" \
        --filter "label=com.docker.compose.service=$1" \
        | head -n1
}

status_of() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

APP=''
WEB=''

preflight() {
    step 'Finding the running stack'

    command -v docker >/dev/null || die 'docker is not on PATH.'
    command -v curl >/dev/null || die 'curl is not on PATH.'

    APP="$(container_for app)"
    WEB="$(container_for web)"

    [[ -n "$APP" ]] || die "No running 'app' container in compose project '${PROJECT}'. Set SMOKE_PROJECT."
    [[ -n "$WEB" ]] || die "No running 'web' container in compose project '${PROJECT}'. Set SMOKE_PROJECT."

    note "project ${PROJECT}, app ${APP:0:12}, web ${WEB:0:12}, url ${BASE_URL}"

    local env
    env="$(docker exec "$APP" php -r 'echo getenv("APP_ENV") ?: "unset";' 2>/dev/null)"

    [[ "$env" == 'production' ]] \
        || die "APP_ENV is '${env}', not production. This script tests a production stack; against a development one it proves nothing."
}

# ---------------------------------------------------------------------------

check_runs_unprivileged() {
    step 'The application does not run as root'

    local uid user
    uid="$(docker exec "$APP" id -u 2>/dev/null)"
    user="$(docker exec "$APP" id -un 2>/dev/null)"

    if [[ "$uid" == '0' ]]; then
        bad "the app container runs as root — every writability check below would pass for the wrong reason"
    else
        pass "runs as ${user} (uid ${uid})"
    fi
}

check_opcache() {
    step 'Opcache is in its production configuration'

    # The inverse of the development gotcha: with validate_timestamps off, PHP
    # serves whatever it first loaded for the life of the container. That is
    # correct here, and it is also why a production container must be
    # recreated rather than restarted after a code change.
    local value
    value="$(docker exec "$APP" php -r 'echo ini_get("opcache.validate_timestamps");' 2>/dev/null)"

    check 'opcache.validate_timestamps is 0' '0' "${value:-unset}"
}

check_writable_paths() {
    step 'Every path the application must write to is writable'

    note 'the check that would have caught the backup-directory outage'

    local path result
    for path in "${WRITABLE_PATHS[@]}"; do
        result="$(docker exec "$APP" sh -c "test -d '${path}' || { echo missing; exit 0; }; test -w '${path}' && echo writable || echo 'not writable'" 2>/dev/null)"

        if [[ "$result" == 'writable' ]]; then
            pass "${path}"
        else
            bad "${path} — ${result:-could not be checked}"
        fi
    done
}

check_backup_runs() {
    step 'A backup archive can actually be written'

    note 'end-to-end proof of the above for the path that broke'

    local before after created output
    before="$(docker exec "$APP" sh -c 'ls storage/app/backups 2>/dev/null | wc -l')"

    if ! output="$(docker exec "$APP" php artisan backup:run 2>&1)"; then
        bad "backup:run failed: $(printf '%s' "$output" | tail -n3 | tr '\n' ' ')"
        return
    fi

    after="$(docker exec "$APP" sh -c 'ls storage/app/backups 2>/dev/null | wc -l')"

    if [[ "$after" -le "$before" ]]; then
        bad 'backup:run reported success but wrote no archive'
        return
    fi

    created="$(docker exec "$APP" sh -c 'ls -t storage/app/backups | head -n1')"

    # Readable, non-empty, and actually a gzip — a truncated archive is the
    # failure mode that looks like success.
    if docker exec "$APP" sh -c "gzip -t 'storage/app/backups/${created}'" 2>/dev/null; then
        pass "wrote ${created}, and it is a valid archive"
    else
        bad "wrote ${created}, but it is not a readable gzip"
    fi

    docker exec "$APP" sh -c "rm -f 'storage/app/backups/${created}'" 2>/dev/null
    note 'test archive removed'
}

check_site_responds() {
    step 'The site responds, with its assets'

    check 'the homepage returns 200' '200' "$(status_of "$BASE_URL/")"

    # The manifest is baked into the image by the build stage. Without it the
    # Vite helper throws on every rendered page, so this failing means the
    # front end was never built into the image.
    local manifest
    manifest="$(docker exec "$APP" sh -c 'test -f public/build/manifest.json && echo present || echo missing' 2>/dev/null)"
    check 'the built asset manifest is in the image' 'present' "$manifest"

    local asset
    asset="$(curl -s "$BASE_URL/" | grep -o '/build/assets/[A-Za-z0-9._-]*\.js' | head -n1)"

    if [[ -z "$asset" ]]; then
        skip 'no /build/ asset referenced by the homepage to fetch'
    else
        check "the asset ${asset##*/} is served" '200' "$(status_of "$BASE_URL$asset")"
    fi
}

check_debug_is_off() {
    step 'Debug output is off'

    # A stack trace on a production 404 leaks paths, versions and often
    # configuration. The framework's debug page is unmistakable.
    local body
    body="$(curl -s "$BASE_URL/this-path-does-not-exist-$$")"

    if printf '%s' "$body" | grep -qiE 'whoops|stack trace|vendor/laravel/framework'; then
        bad 'a 404 rendered a debug page — APP_DEBUG is on in production'
    else
        pass 'a 404 renders no stack trace'
    fi
}

check_security_headers() {
    step 'Security headers survive in every location that sets one'

    note 'nginx add_header does not merge: one in a location discards all inherited'

    local -a targets=("/")

    local asset
    asset="$(curl -s "$BASE_URL/" | grep -o '/build/assets/[A-Za-z0-9._-]*\.js' | head -n1)"
    [[ -n "$asset" ]] && targets+=("$asset")

    local target headers header
    for target in "${targets[@]}"; do
        headers="$(curl -s -D - -o /dev/null "$BASE_URL$target" | tr '[:upper:]' '[:lower:]')"

        for header in "${SECURITY_HEADERS[@]}"; do
            if printf '%s' "$headers" | grep -q "^${header}:"; then
                pass "${target} sends ${header}"
            else
                bad "${target} is missing ${header}"
            fi
        done
    done
}

check_internal_locations() {
    step 'The internal locations are unreachable from outside'

    note 'these stream gated media and backup archives; only Laravel may name them'

    local path
    for path in /__media/ /__backup/ /storage/app/private; do
        check "${path} is not servable" '404' "$(status_of "$BASE_URL$path")"
    done
}

check_crawler_files() {
    step 'robots.txt and sitemap.xml are generated'

    note 'both are routes, not files — an exact-match location without try_files 404s'

    check 'robots.txt returns 200' '200' "$(status_of "$BASE_URL/robots.txt")"
    check 'sitemap.xml returns 200' '200' "$(status_of "$BASE_URL/sitemap.xml")"

    # The Sitemap: line has to carry an absolute URL, which is the whole
    # reason neither is a static file.
    if curl -s "$BASE_URL/robots.txt" | grep -qi '^sitemap: https\?://'; then
        pass 'robots.txt names the sitemap with an absolute URL'
    else
        bad 'robots.txt has no absolute Sitemap: line'
    fi
}

# Discovered through the site's own public surface rather than the database,
# because production has no tinker to ask.
discover_media_url() {
    if [[ -n "${SMOKE_MEDIA_URL:-}" ]]; then
        printf '%s' "$SMOKE_MEDIA_URL"
        return
    fi

    local page match ulid
    while read -r page; do
        [[ -n "$page" ]] || continue

        # The link lives in the Inertia JSON payload (slashes escaped), so
        # match the 26-char Crockford-base32 ULID itself rather than
        # unescaping — also avoids false-matching `downloads_description`.
        match="$(curl -s "$page" \
            | grep -o 'downloads[^0-9A-Za-z]\{1,2\}[0-9A-Z]\{26\}' \
            | head -n1)"

        if [[ -n "$match" ]]; then
            ulid="${match: -26}"
            printf '/downloads/%s' "$ulid"
            return
        fi
    done < <(curl -s "$BASE_URL/sitemap.xml" | grep -o '<loc>[^<]*</loc>' | sed 's|</\?loc>||g' | head -n 25)
}

check_gated_media() {
    step 'Gated media streams through nginx'

    local href
    href="$(discover_media_url)"

    if [[ -z "$href" ]]; then
        skip 'no published download found on this site — add content and re-run, or set SMOKE_MEDIA_URL'
        note 'this is the check that proves X-Accel-Redirect works as a different user from a read-only mount'
        return
    fi

    note "probing ${href}"

    local headers
    headers="$(curl -s -D - -o /dev/null "$BASE_URL$href" | tr '[:upper:]' '[:lower:]')"

    local code
    code="$(printf '%s' "$headers" | head -n1 | awk '{print $2}')"
    check 'a published download returns 200' '200' "$code"

    # nginx serving the bytes rather than PHP: an ETag and byte-range support
    # are what PHP's own streaming path does not produce.
    if printf '%s' "$headers" | grep -q '^etag:'; then
        pass 'nginx served it (ETag present)'
    else
        bad 'no ETag — PHP streamed this, so MEDIA_X_ACCEL is off in production'
    fi

    if printf '%s' "$headers" | grep -q '^accept-ranges: *bytes'; then
        pass 'byte ranges are advertised'
    else
        bad 'no Accept-Ranges — seeking in a video will not work'
    fi

    # The window asked for may be larger than the file the site happens to
    # publish, and that is not a failure: nginx answers 206 with everything it
    # has. Expect min(window, size), or this check goes red on any site whose
    # first download is a small worksheet — which is what it did.
    local total window=100
    total="$(printf '%s' "$headers" | grep -i '^content-length:' | head -n1 | tr -cd '0-9')"
    [[ -n "$total" && "$total" -gt 0 && "$total" -lt "$window" ]] && window="$total"

    local range_code range_len
    range_code="$(curl -s -o /dev/null -w '%{http_code}' -H 'Range: bytes=0-99' "$BASE_URL$href")"
    range_len="$(curl -s -H 'Range: bytes=0-99' "$BASE_URL$href" | wc -c)"

    check 'a Range request returns 206' '206' "$range_code"
    check "and the first ${window} bytes" "$window" "$(printf '%s' "$range_len" | tr -d ' ')"
}

main() {
    printf '\n  %sProduction smoke test%s\n' "$C_BOLD" "$C_OFF"

    preflight

    check_runs_unprivileged
    check_opcache
    check_writable_paths
    check_backup_runs
    check_site_responds
    check_debug_is_off
    check_security_headers
    check_internal_locations
    check_crawler_files
    check_gated_media

    printf '\n'

    if [[ "$FAILURES" -gt 0 ]]; then
        printf '  %s%s check(s) failed.%s\n\n' "$C_RED" "$FAILURES" "$C_OFF"
        exit 1
    fi

    if [[ "$SKIPS" -gt 0 ]]; then
        printf '  %sAll checks passed, %s skipped.%s\n\n' "$C_BOLD" "$SKIPS" "$C_OFF"
        exit 0
    fi

    printf '  %sAll checks passed.%s\n\n' "$C_BOLD" "$C_OFF"
}

main "$@"
