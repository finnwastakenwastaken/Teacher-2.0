#!/usr/bin/env bash
#
# One-command bring-up on a fresh Debian 13 (Trixie) machine.
#
#   sudo ./install.sh
#
# Read this file before running it. It is deliberately not a `curl | bash`
# one-liner: it installs Docker, writes secrets and changes the firewall, and
# all three deserve to be read first.
#
# Safe to re-run. A second run updates rather than duplicating: it skips what
# is already installed, refuses to overwrite an existing .env, and re-applies
# the same firewall rules.
#
# Every prompt has a non-interactive override, so the whole thing can be
# scripted:
#
#   TEACHER_DOMAIN=les.example.nl \
#   TEACHER_TUNNEL_TOKEN=eyJhIjoi… \
#   TEACHER_ASSUME_YES=1 sudo -E ./install.sh
#
# Overrides:
#   TEACHER_DIR             where to install            (default: this directory,
#                                                        or /opt/teacher when
#                                                        run from elsewhere)
#   TEACHER_REPO            repository to clone, only used when TEACHER_DIR is
#                           empty and this script is not inside a checkout
#   TEACHER_DOMAIN          public hostname
#   TEACHER_USE_TUNNEL      1 (default) or 0 for "I run my own reverse proxy"
#   TEACHER_TUNNEL_TOKEN    Cloudflare Tunnel token
#   TEACHER_SETUP_TOKEN     value for ADMIN_SETUP_TOKEN; "generate" (default)
#                           makes one up, "none" leaves the claim window open
#   TEACHER_SKIP_FIREWALL   1 to leave ufw alone
#   TEACHER_RESTORE         path to a backup archive to put into the new site.
#                           This is how a site moves to another machine:
#                           install, restore, done — no claim screen, no token
#   TEACHER_SKIP_DOCKER     1 to assume Docker is already installed
#   TEACHER_ASSUME_YES      1 to accept every default without asking

set -euo pipefail

readonly REQUIRED_RAM_MB=3800   # 4 GB, minus what firmware and the kernel take
readonly REQUIRED_DISK_GB=20
readonly HEALTH_TIMEOUT=300

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly SCRIPT_DIR

TEACHER_DIR="${TEACHER_DIR:-}"
TEACHER_REPO="${TEACHER_REPO:-}"
TEACHER_DOMAIN="${TEACHER_DOMAIN:-}"
TEACHER_USE_TUNNEL="${TEACHER_USE_TUNNEL:-}"
TEACHER_TUNNEL_TOKEN="${TEACHER_TUNNEL_TOKEN:-}"
TEACHER_SETUP_TOKEN="${TEACHER_SETUP_TOKEN:-generate}"
TEACHER_SKIP_FIREWALL="${TEACHER_SKIP_FIREWALL:-0}"
TEACHER_RESTORE="${TEACHER_RESTORE:-}"
TEACHER_SKIP_DOCKER="${TEACHER_SKIP_DOCKER:-0}"
TEACHER_ASSUME_YES="${TEACHER_ASSUME_YES:-0}"

ENV_FILE=''
GENERATED_SETUP_TOKEN=''
RESTORED=0
COMPOSE_PROFILE_ARGS=()

# ---------------------------------------------------------------------------
# Output
# ---------------------------------------------------------------------------

if [[ -t 1 ]]; then
    C_BOLD=$'\033[1m'; C_DIM=$'\033[2m'; C_RED=$'\033[31m'
    C_GREEN=$'\033[32m'; C_YELLOW=$'\033[33m'; C_OFF=$'\033[0m'
else
    C_BOLD=''; C_DIM=''; C_RED=''; C_GREEN=''; C_YELLOW=''; C_OFF=''
fi

step()  { printf '\n%s==>%s %s%s%s\n' "$C_GREEN" "$C_OFF" "$C_BOLD" "$*" "$C_OFF"; }
info()  { printf '    %s\n' "$*"; }
note()  { printf '    %s%s%s\n' "$C_DIM" "$*" "$C_OFF"; }
warn()  { printf '%s !! %s%s\n' "$C_YELLOW" "$*" "$C_OFF" >&2; }
fail()  { printf '%s !! %s%s\n' "$C_RED" "$*" "$C_OFF" >&2; exit 1; }

confirm() {
    local prompt=$1
    [[ "$TEACHER_ASSUME_YES" == '1' ]] && return 0
    local answer
    read -r -p "    ${prompt} [j/N] " answer </dev/tty || return 1
    [[ "$answer" =~ ^[jJyY]$ ]]
}

# Prompt with a default. Returns the default without asking in unattended mode.
ask() {
    local prompt=$1 default=${2:-} answer
    if [[ "$TEACHER_ASSUME_YES" == '1' ]]; then
        printf '%s' "$default"
        return
    fi
    if [[ -n "$default" ]]; then
        read -r -p "    ${prompt} [${default}]: " answer </dev/tty || true
    else
        read -r -p "    ${prompt}: " answer </dev/tty || true
    fi
    printf '%s' "${answer:-$default}"
}

# Read a secret without echoing it. Never accept one as a command-line
# argument: arguments are visible in `ps` and in shell history.
ask_secret() {
    local prompt=$1 answer
    read -r -s -p "    ${prompt}: " answer </dev/tty || true
    printf '\n' >&2
    printf '%s' "$answer"
}

# ---------------------------------------------------------------------------
# .env editing
#
# awk with a literal prefix match rather than sed, so nothing in a generated
# secret or a tunnel token can be read as a regular expression or collide with
# the substitution delimiter. Values reach awk through the environment, so they
# are never re-interpreted for escape sequences either.
# ---------------------------------------------------------------------------
set_env() {
    local key=$1 value=$2
    SET_ENV_KEY="$key" SET_ENV_VALUE="$value" awk '
        BEGIN { key = ENVIRON["SET_ENV_KEY"]; value = ENVIRON["SET_ENV_VALUE"]; found = 0 }
        index($0, key "=") == 1 { print key "=" value; found = 1; next }
        { print }
        END { if (!found) print key "=" value }
    ' "$ENV_FILE" >"${ENV_FILE}.tmp"
    mv "${ENV_FILE}.tmp" "$ENV_FILE"
    chmod 600 "$ENV_FILE"
}

get_env() {
    local key=$1
    SET_ENV_KEY="$key" awk '
        BEGIN { key = ENVIRON["SET_ENV_KEY"] }
        index($0, key "=") == 1 { print substr($0, length(key) + 2); exit }
    ' "$ENV_FILE"
}

# ---------------------------------------------------------------------------
# 1. Preflight
# ---------------------------------------------------------------------------

preflight() {
    step 'Checking this machine'

    [[ "$(id -u)" == '0' ]] || fail 'Run this as root, or with sudo.'

    if [[ -r /etc/os-release ]]; then
        # shellcheck disable=SC1091
        . /etc/os-release
        info "Operating system: ${PRETTY_NAME:-unknown}"
        if [[ "${ID:-}" != 'debian' ]]; then
            warn "This installer is written for Debian 13. ${ID:-This system} may work, but the Docker repository below is Debian's."
            confirm 'Continue anyway?' || fail 'Stopped.'
        elif [[ "${VERSION_ID:-}" != '13' ]]; then
            warn "Debian ${VERSION_ID:-?} found, 13 (Trixie) expected."
            confirm 'Continue anyway?' || fail 'Stopped.'
        fi
        DEBIAN_CODENAME="${VERSION_CODENAME:-trixie}"
    else
        warn 'No /etc/os-release — assuming Debian 13.'
        DEBIAN_CODENAME=trixie
    fi

    local ram_mb
    ram_mb=$(awk '/^MemTotal:/ { printf "%d", $2 / 1024 }' /proc/meminfo)
    info "Memory: ${ram_mb} MB"
    if (( ram_mb < REQUIRED_RAM_MB )); then
        # A warning rather than an abort: swap can carry a short build, and
        # refusing to install on a machine that would work is worse than
        # letting the operator decide.
        warn "The frontend is built on this machine and needs about 4 GB. Expect the build to fail or to need swap."
        confirm 'Continue anyway?' || fail 'Stopped.'
    fi

    local disk_gb
    disk_gb=$(df -BG --output=avail / | tail -1 | tr -dc '0-9')
    info "Free disk space: ${disk_gb} GB"
    if (( disk_gb < REQUIRED_DISK_GB )); then
        warn "Less than ${REQUIRED_DISK_GB} GB free. Video fills this up quickly."
        confirm 'Continue anyway?' || fail 'Stopped.'
    fi

    # Deliberately no network check here. A fresh Debian has no curl, so
    # anything that reaches out has to wait until apt has run — see
    # install_base_packages, where `apt-get update` is itself the connectivity
    # test, and install_docker, which checks the one host it needs.
}

# ---------------------------------------------------------------------------
# 2. Base packages
# ---------------------------------------------------------------------------

install_base_packages() {
    step 'Installing base packages'
    export DEBIAN_FRONTEND=noninteractive

    # This doubles as the connectivity test: nothing else in this installer
    # can work if the machine cannot reach the Debian mirrors.
    apt-get update -qq || fail 'apt-get update failed. Check this machine'"'"'s network and DNS before continuing.'

    apt-get upgrade -y -qq
    apt-get install -y -qq ca-certificates curl git ufw openssl unattended-upgrades
    info 'ca-certificates curl git ufw openssl unattended-upgrades'
}

enable_unattended_upgrades() {
    step 'Enabling automatic security updates'
    dpkg-reconfigure -f noninteractive unattended-upgrades
    info 'unattended-upgrades is on'
}

# ---------------------------------------------------------------------------
# 3. Docker
# ---------------------------------------------------------------------------

install_docker() {
    step 'Installing Docker'

    if [[ "$TEACHER_SKIP_DOCKER" == '1' ]]; then
        note 'TEACHER_SKIP_DOCKER=1 — skipped'
    elif docker compose version >/dev/null 2>&1; then
        note "Already installed: $(docker --version)"
    else
        install -m 0755 -d /etc/apt/keyrings
        curl -fsSL --max-time 30 https://download.docker.com/linux/debian/gpg \
            -o /etc/apt/keyrings/docker.asc \
            || fail 'Could not reach download.docker.com. Check outbound HTTPS and DNS.'
        chmod a+r /etc/apt/keyrings/docker.asc

        # Debian 13 uses the deb822 format. The older single-line
        # `deb [signed-by=…]` form still parses, but new installs should use
        # this one.
        cat >/etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/debian
Suites: ${DEBIAN_CODENAME}
Components: stable
Signed-By: /etc/apt/keyrings/docker.asc
EOF

        apt-get update -qq
        apt-get install -y -qq docker-ce docker-ce-cli containerd.io \
            docker-buildx-plugin docker-compose-plugin
        info "Installed: $(docker --version)"
    fi

    docker compose version >/dev/null 2>&1 \
        || fail 'docker compose is not available. Install it and re-run.'

    systemctl enable --now docker >/dev/null 2>&1 || true
}

# ---------------------------------------------------------------------------
# 4. The application directory
# ---------------------------------------------------------------------------

resolve_directory() {
    step 'Locating the application'

    if [[ -z "$TEACHER_DIR" ]]; then
        if [[ -f "${SCRIPT_DIR}/compose.yaml" ]]; then
            TEACHER_DIR="$SCRIPT_DIR"
        else
            TEACHER_DIR=/opt/teacher
        fi
    fi

    if [[ -f "${TEACHER_DIR}/compose.yaml" ]]; then
        info "Using ${TEACHER_DIR}"
        if [[ -d "${TEACHER_DIR}/.git" ]] && [[ "$TEACHER_DIR" != "$SCRIPT_DIR" ]]; then
            git -C "$TEACHER_DIR" pull --ff-only || warn 'Could not fast-forward; leaving the checkout as it is.'
        fi
    else
        [[ -n "$TEACHER_REPO" ]] || fail \
            "No checkout at ${TEACHER_DIR}. Clone the repository there first, or set TEACHER_REPO."
        info "Cloning ${TEACHER_REPO} into ${TEACHER_DIR}"
        git clone "$TEACHER_REPO" "$TEACHER_DIR"
    fi

    cd "$TEACHER_DIR"
    ENV_FILE="${TEACHER_DIR}/.env"
    [[ -f compose.yaml ]] || fail "compose.yaml not found in ${TEACHER_DIR}."
}

# ---------------------------------------------------------------------------
# 5. Configuration
# ---------------------------------------------------------------------------

configure_environment() {
    step 'Writing .env'

    if [[ -f "$ENV_FILE" ]]; then
        # Never silently rewrite a live configuration: APP_KEY is in here, and
        # replacing it makes every existing session and cookie unreadable.
        warn "${ENV_FILE} already exists."
        info 'Keeping it. Existing settings, including APP_KEY, are left alone.'
        if [[ -z "$(get_env APP_KEY)" ]]; then
            fail 'APP_KEY is empty in the existing .env. Fix that by hand, or move the file aside and re-run.'
        fi
        # Added to installations that predate it. Without a value of its own
        # the passkey handle secret falls back to APP_KEY, so rotating the
        # application key would silently unenrol every passkey — and key
        # rotation is a supported operation here (APP_PREVIOUS_KEYS exists for
        # exactly that). Writing it now, from the current APP_KEY, keeps the
        # passkeys that are already enrolled working while making the two
        # independent from here on.
        if [[ -z "$(get_env PASSKEYS_USER_HANDLE_SECRET)" ]]; then
            set_env PASSKEYS_USER_HANDLE_SECRET "$(get_env APP_KEY)"
            info 'Pinned PASSKEYS_USER_HANDLE_SECRET so a future APP_KEY rotation cannot unenrol passkeys'
        fi
        chmod 600 "$ENV_FILE"
        collect_tunnel_choice_from_env
        return
    fi

    cp .env.example "$ENV_FILE"
    chmod 600 "$ENV_FILE"

    # -- Secrets. Generated here, never prompted for and never echoed. --------
    #
    # openssl's base64 output can contain + and /, which .env handles fine
    # unquoted. The database password is hex on purpose: its value is
    # interpolated by Compose into POSTGRES_PASSWORD, and hex cannot collide
    # with anything Compose treats as syntax.
    set_env APP_KEY "base64:$(openssl rand -base64 32)"
    set_env DB_PASSWORD "$(openssl rand -hex 24)"
    # Its own secret rather than Fortify's fallback to APP_KEY. The two have
    # different lifetimes: APP_KEY can be rotated (that is what
    # APP_PREVIOUS_KEYS is for) and the cost of rotating it is one round of
    # re-logging-in, whereas changing the passkey handle secret unenrols every
    # passkey with no way to get them back. Tying them together would make the
    # cheap operation quietly do the expensive one.
    set_env PASSKEYS_USER_HANDLE_SECRET "$(openssl rand -hex 32)"
    info 'Generated APP_KEY, a database password and a passkey handle secret'

    # -- Domain --------------------------------------------------------------
    local domain
    domain="${TEACHER_DOMAIN:-$(ask 'Public hostname (for example les.example.nl)' '')}"
    [[ -n "$domain" ]] || fail 'A hostname is required — it goes into APP_URL and into every link the site generates.'
    domain="${domain#http://}"; domain="${domain#https://}"; domain="${domain%%/*}"
    set_env APP_URL "https://${domain}"
    set_env APP_NAME "Lesmateriaal"
    info "APP_URL=https://${domain}"

    # -- Tunnel --------------------------------------------------------------
    collect_tunnel_choice

    if [[ "$TEACHER_USE_TUNNEL" == '1' ]]; then
        if [[ -z "$TEACHER_TUNNEL_TOKEN" ]]; then
            info 'Paste the Cloudflare Tunnel token. It will not be shown.'
            TEACHER_TUNNEL_TOKEN="$(ask_secret 'Tunnel token')"
        fi
        # Cloudflare's tokens are a base64 blob. Checking the shape catches the
        # usual mistakes — a truncated paste, or the tunnel *name* pasted
        # instead of the token — without pretending to validate it for real.
        if [[ ! "$TEACHER_TUNNEL_TOKEN" =~ ^[A-Za-z0-9+/=_-]{40,}$ ]]; then
            fail 'That does not look like a Cloudflare Tunnel token. Copy it from Zero Trust → Networks → Tunnels.'
        fi
        set_env CLOUDFLARE_TUNNEL_TOKEN "$TEACHER_TUNNEL_TOKEN"
        info 'Tunnel token stored in .env'
    fi

    # -- Claim window --------------------------------------------------------
    #
    # ADMIN_EMAIL and ADMIN_PASSWORD are deliberately left blank. Setting only
    # the address makes `admin:seed` refuse to start the container — both are
    # required together — and the alternative, prompting for a password and
    # writing it to disk in plain text, is worse than the browser claim screen.
    # So the account is claimed in the browser, and this token closes the
    # window in the meantime.
    case "$TEACHER_SETUP_TOKEN" in
        none|'')
            warn 'No ADMIN_SETUP_TOKEN. Anyone who reaches the site before you can claim the only account.'
            ;;
        generate)
            GENERATED_SETUP_TOKEN="$(openssl rand -hex 16)"
            set_env ADMIN_SETUP_TOKEN "$GENERATED_SETUP_TOKEN"
            info 'Generated an ADMIN_SETUP_TOKEN — printed once at the end'
            ;;
        *)
            set_env ADMIN_SETUP_TOKEN "$TEACHER_SETUP_TOKEN"
            info 'Stored the ADMIN_SETUP_TOKEN you supplied'
            ;;
    esac

    chmod 600 "$ENV_FILE"
    info "${ENV_FILE} written, mode 600"
}

collect_tunnel_choice() {
    if [[ -z "$TEACHER_USE_TUNNEL" ]]; then
        if confirm 'Use a Cloudflare Tunnel? (No means you run your own reverse proxy in front of 127.0.0.1:8080.)'; then
            TEACHER_USE_TUNNEL=1
        else
            TEACHER_USE_TUNNEL=0
        fi
    fi
    [[ "$TEACHER_USE_TUNNEL" == '1' ]] && COMPOSE_PROFILE_ARGS=(--profile tunnel)
    return 0
}

collect_tunnel_choice_from_env() {
    if [[ -n "$(get_env CLOUDFLARE_TUNNEL_TOKEN)" ]]; then
        TEACHER_USE_TUNNEL=1
        COMPOSE_PROFILE_ARGS=(--profile tunnel)
        note 'Existing .env has a tunnel token — starting with the tunnel profile'
    else
        TEACHER_USE_TUNNEL=0
    fi
}

# ---------------------------------------------------------------------------
# 6. Build and start
# ---------------------------------------------------------------------------

start_stack() {
    step 'Building and starting (this takes a few minutes the first time)'
    docker compose "${COMPOSE_PROFILE_ARGS[@]}" up -d --build

    step 'Waiting for the site to answer'
    # Migrations and seeders run from the container entrypoint, so a healthy
    # response means the schema is in place too.
    local waited=0
    until curl -fsS --max-time 5 -o /dev/null http://127.0.0.1:8080/up; do
        (( waited += 5 ))
        if (( waited >= HEALTH_TIMEOUT )); then
            warn "No healthy response after ${HEALTH_TIMEOUT}s. Recent logs:"
            docker compose logs --tail=40 app >&2 || true
            fail 'The stack did not come up.'
        fi
        sleep 5
    done
    info "Healthy after ${waited}s"
}

# ---------------------------------------------------------------------------
# 7. Firewall
# ---------------------------------------------------------------------------

configure_firewall() {
    step 'Configuring the firewall'

    if [[ "$TEACHER_SKIP_FIREWALL" == '1' ]]; then
        note 'TEACHER_SKIP_FIREWALL=1 — skipped'
        return
    fi

    # SSH first, always, and never enable the firewall if that did not work —
    # the worst outcome of this whole installer is locking the operator out of
    # a machine they reach only over SSH. The `OpenSSH` application profile
    # ships with openssh-server, so fall back to the port when it is absent.
    if ! ufw allow OpenSSH >/dev/null 2>&1 && ! ufw allow 22/tcp >/dev/null 2>&1; then
        warn 'Could not add an SSH rule to ufw, so the firewall is being left alone.'
        warn 'Enabling it now could lock you out. Configure ufw by hand.'
        return
    fi
    info 'SSH allowed'

    if [[ "$TEACHER_USE_TUNNEL" != '1' ]]; then
        note 'No tunnel configured, so inbound 80/443 is left alone — your reverse proxy needs it.'
        note 'Enable ufw yourself once your proxy rules are in place.'
        return
    fi

    # The tunnel dials out, so nothing needs to reach this machine from the
    # network. Say so explicitly rather than relying on it.
    ufw deny 80/tcp >/dev/null
    ufw deny 443/tcp >/dev/null

    if ufw --force enable >/dev/null 2>&1; then
        info 'Inbound 80 and 443 denied; ufw enabled'
    else
        warn 'ufw refused to enable. The rules are stored; run "ufw enable" once you know why.'
    fi
}

# ---------------------------------------------------------------------------
# 8. Done
# ---------------------------------------------------------------------------

# ---------------------------------------------------------------------------
# Moving an existing site onto this machine.
#
# TEACHER_RESTORE turns the installer into "put my site on this box": it
# brings a fresh instance up as normal and then replaces its empty database
# and media with an archive's. The admin account comes back with the data, so
# there is no claim screen to race and no setup token to hand out.
#
# The safety archive restore.sh normally takes is skipped: this site is
# seconds old and archiving an empty database to protect it is theatre.
# ---------------------------------------------------------------------------
restore_backup() {
    [[ -n "$TEACHER_RESTORE" ]] || return 0

    step 'Restoring the backup'

    [[ -f "$TEACHER_RESTORE" ]] || fail "There is no file at ${TEACHER_RESTORE}."
    [[ -x "${TEACHER_DIR}/restore.sh" ]] || fail "restore.sh is missing from ${TEACHER_DIR}."

    local script="${TEACHER_DIR}/restore.sh"

    TEACHER_DIR="$TEACHER_DIR" TEACHER_SKIP_BACKUP=1 TEACHER_ASSUME_YES=1 \
        "$script" "$TEACHER_RESTORE" \
        || fail 'The restore failed. The site is up but still empty — fix the archive and run ./restore.sh again.'

    RESTORED=1
}

summary() {
    local url
    url="$(get_env APP_URL)"

    # A restored site already has its account, its content and its branding.
    # Telling the operator to claim an account that exists would send them to
    # a screen that refuses them, and printing a setup token they cannot use
    # is worse than printing nothing.
    if [[ "$RESTORED" == '1' ]]; then
        printf '\n%s%s%s\n' "$C_GREEN$C_BOLD" '  De site draait, met de back-up erin.' "$C_OFF"
        printf '\n'
        printf '    Adres            %s\n' "$url"
        printf '    Map              %s\n' "$TEACHER_DIR"
        printf '    Instellingen     %s  (mode 600 — back-uppen)\n' "$ENV_FILE"
        printf '\n'
        printf '    Log in met het account uit de back-up. Kwijt? Dan:\n'
        printf '      docker compose exec app php artisan admin:reset-password\n'
        printf '\n'
        printf '  Bijwerken doe je later met:  sudo ./update.sh\n\n'
        return
    fi

    printf '\n%s%s%s\n' "$C_GREEN$C_BOLD" '  De site draait.' "$C_OFF"
    printf '\n'
    printf '    Adres            %s\n' "$url"
    printf '    Map              %s\n' "$TEACHER_DIR"
    printf '    Instellingen     %s  (mode 600 — back-uppen)\n' "$ENV_FILE"
    printf '\n'
    printf '  %sEis het beheerdersaccount nu op:%s\n' "$C_BOLD" "$C_OFF"
    printf '    %s/admin/claim\n' "$url"

    # Read the state back from .env rather than from this run's variables: on a
    # re-run nothing was generated, and saying "no token is set" when one is
    # would be exactly backwards.
    if [[ -n "$GENERATED_SETUP_TOKEN" ]]; then
        printf '\n'
        printf '    Het opeisscherm vraagt ook om deze code:\n'
        printf '      %s%s%s\n' "$C_BOLD" "$GENERATED_SETUP_TOKEN" "$C_OFF"
        printf '    Deze staat verder alleen in .env en wordt niet nog een keer getoond.\n'
        printf '    Geef hem door via een ander kanaal dan het adres van de site.\n'
    elif [[ -n "$(get_env ADMIN_SETUP_TOKEN)" ]]; then
        printf '\n'
        printf '    Het opeisscherm vraagt ook om de ADMIN_SETUP_TOKEN uit .env.\n'
    else
        printf '\n'
        printf '    %sEr is geen ADMIN_SETUP_TOKEN ingesteld: wie het adres kent kan\n' "$C_YELLOW"
        printf '    het account opeisen. Doe dit dus meteen.%s\n' "$C_OFF"
    fi

    printf '\n'
    printf '  Verder lezen:\n'
    printf '    docs/beheerdersgids.md            voor de docent\n'
    printf '    docs/onderhoud-en-beveiliging.md  back-ups, updates, herstel\n'
    printf '\n'
    printf '  Bijwerken doe je later met:  sudo ./update.sh\n\n'
}

# ---------------------------------------------------------------------------

main() {
    printf '%s%s%s\n' "$C_BOLD" 'Teacher 2.0 — installer' "$C_OFF"
    printf '%sThis will: check the machine, install Docker, write .env with generated\n' "$C_DIM"
    printf 'secrets, build and start the stack, and close inbound 80/443 in ufw.%s\n' "$C_OFF"

    preflight
    install_base_packages
    enable_unattended_upgrades
    install_docker
    resolve_directory
    configure_environment
    start_stack
    restore_backup

    # Not fatal. By this point the site is up and the setup token has been
    # generated, and aborting here would hide the summary — which is the only
    # place that token is ever printed.
    configure_firewall || warn 'The firewall step did not finish. Check "ufw status" yourself.'

    summary
}

main "$@"
