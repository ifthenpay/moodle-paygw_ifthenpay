#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Moodle container boot sequence. Idempotent: safe to run on every start.
#
#   0. rewrite Xdebug's ini and Apache's error.log from the environment
#   1. sync the pinned Moodle release from the image into the shared volume
#   2. wait for the database
#   3. install Moodle (first boot) or reuse the existing config.php
#   4. keep wwwroot / developer settings in sync with the environment
#   5. run pending core + plugin upgrades
#   6. enable and (optionally) configure the ifthenpay gateway
#   7. hand over to Apache and flag the container as ready
#
# Everything after step 3 is best effort: a failure is reported but still lets
# Apache start, so the site stays reachable for debugging.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

DIRROOT="${MOODLE_DIRROOT:-/var/www/html}"
DATAROOT="${MOODLE_DATAROOT:-/var/www/moodledata}"
PHPUNIT_DATAROOT="${MOODLE_PHPUNIT_DATAROOT:-/var/www/phpunitdata}"
SRCROOT=/opt/moodle-src
CONFIG="${DIRROOT}/config.php"
READY_FLAG=/run/moodle-ready
PLUGIN_RELPATH=public/payment/gateway/ifthenpay
DEV_SETUP=/usr/local/lib/ifthenpay/dev-setup.php

MOODLE_DEV_MODE="${MOODLE_DEV_MODE:-1}"
MOODLE_AUTO_UPGRADE="${MOODLE_AUTO_UPGRADE:-1}"

needs_purge=0

log()  { printf '\033[1;36m[moodle]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[moodle]\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31m[moodle]\033[0m %s\n' "$*" >&2; exit 1; }

# Run a command as the web server user (owns dirroot, dataroot and the mount).
if command -v runuser >/dev/null 2>&1; then
    as_web() { runuser -u www-data -- "$@"; }
else
    as_web() { su www-data -s /bin/bash -c "$(printf '%q ' "$@")"; }
fi

moodle_cli() {
    local script="$1"; shift
    as_web php "${DIRROOT}/admin/cli/${script}" "$@"
}

# ── 0. Runtime config (Xdebug, error log) ────────────────────────────────────
# Xdebug does not honour the XDEBUG_MODE / XDEBUG_CONFIG environment variables
# once xdebug.mode is already set in php.ini, so the ini file itself has to be
# rewritten from the environment on every start (same reasoning as the
# managed config.php block below).
configure_xdebug() {
    {
        echo "xdebug.mode=${XDEBUG_MODE:-off}"
        echo "xdebug.start_with_request=${XDEBUG_START:-trigger}"
        echo 'xdebug.client_host=ifthenpay-dev'
        echo 'xdebug.client_port=9003'
        echo 'xdebug.discover_client_host=false'
        echo 'xdebug.max_nesting_level=512'
        echo 'xdebug.log_level=7'
        echo 'xdebug.log=/tmp/xdebug.log'
    } > /usr/local/etc/php/conf.d/zz-xdebug.ini
    log "Xdebug configured (mode=${XDEBUG_MODE:-off})."
}

rm -f "${READY_FLAG}"
[ "$(id -u)" = "0" ] || die "The entrypoint must run as root (it drops to www-data itself)."

# The base image symlinks error.log to /dev/stderr, which resolves per-process
# — fine for `docker compose logs`, but `mdl logs` tails the same path from the
# dev container and would just read its own unrelated stderr. A real, shared
# file is needed instead.
#
# Keep it root-owned (only chgrp, never chown): on this host, once a file's
# OWNER changes away from root, root can no longer write to it (not even via
# chmod 666) — that broke Apache's own error log the first time this was
# tried. Group-write for www-data (PHP) plus world-read (`mdl logs`) is enough
# and doesn't hit that quirk.
fix_apache_error_log() {
    local error_log=/var/log/apache2/error.log
    if [ -L "${error_log}" ] || [ "$(stat -c '%U:%G:%a' "${error_log}" 2>/dev/null)" != "root:www-data:664" ]; then
        rm -f "${error_log}"
        touch "${error_log}"
        chgrp www-data "${error_log}"
        chmod 0664 "${error_log}"
        log "Apache error.log is now a real file (mdl logs works from the dev container)."
    fi
}

# ── 1. Core files ────────────────────────────────────────────────────────────
sync_core() {
    local imageversion currentversion
    imageversion="$(cat "${SRCROOT}/.moodle-version")"
    currentversion="$(cat "${DIRROOT}/.moodle-version" 2>/dev/null || true)"

    if [ "${imageversion}" = "${currentversion}" ]; then
        log "Moodle core ${imageversion} already in place."
        return
    fi

    log "Syncing Moodle core ${currentversion:-<empty>} → ${imageversion} ..."
    # config.php is site state; the plugin path is a bind mount from the repo.
    rsync -a --delete \
        --exclude "/config.php" \
        --exclude "/.moodle-version" \
        --exclude "/${PLUGIN_RELPATH}" \
        "${SRCROOT}/" "${DIRROOT}/"
    echo "${imageversion}" > "${DIRROOT}/.moodle-version"
    chown www-data:www-data "${DIRROOT}/.moodle-version"
    needs_purge=1
    log "Moodle core is now ${imageversion}."
}

ensure_dirs() {
    mkdir -p "${DATAROOT}" "${PHPUNIT_DATAROOT}"
    # Only fix ownership when it is wrong: a recursive chown of moodledata is
    # slow once the site has real files in it.
    for dir in "${DATAROOT}" "${PHPUNIT_DATAROOT}"; do
        if [ "$(stat -c '%u' "${dir}")" != "$(id -u www-data)" ]; then
            log "Fixing ownership of ${dir} ..."
            chown -R www-data:www-data "${dir}"
        fi
    done
    chmod 0775 "${DATAROOT}" "${PHPUNIT_DATAROOT}"
}

# ── 2. Database ──────────────────────────────────────────────────────────────
wait_for_db() {
    local attempts=60
    log "Waiting for database ${MOODLE_DBHOST} ..."
    until MYSQL_PWD="${MOODLE_DBPASS}" mysqladmin ping \
            -h"${MOODLE_DBHOST}" -u"${MOODLE_DBUSER}" --skip-ssl --silent >/dev/null 2>&1; do
        attempts=$((attempts - 1))
        [ "${attempts}" -gt 0 ] || die "Database did not become available in time."
        sleep 2
    done
    log "Database is ready."
}

database_is_installed() {
    MYSQL_PWD="${MOODLE_DBPASS}" mysql -h"${MOODLE_DBHOST}" -u"${MOODLE_DBUSER}" \
        --skip-ssl -N -B \
        -e "SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = '${MOODLE_DBNAME}' AND table_name = 'mdl_config';" \
        "${MOODLE_DBNAME}" 2>/dev/null \
        | grep -q '^1$'
}

# ── 3. Installation ──────────────────────────────────────────────────────────
install_moodle() {
    local extra=()
    if database_is_installed; then
        log "Database already contains a Moodle install — writing config.php only."
        extra+=(--skip-database)
    else
        log "Installing Moodle (this takes a couple of minutes on first boot) ..."
    fi

    as_web php "${DIRROOT}/admin/cli/install.php" \
        --lang="${MOODLE_LANG:-en}" \
        --wwwroot="${MOODLE_WWWROOT}" \
        --dataroot="${DATAROOT}" \
        --dbtype=mysqli \
        --dbhost="${MOODLE_DBHOST}" \
        --dbname="${MOODLE_DBNAME}" \
        --dbuser="${MOODLE_DBUSER}" \
        --dbpass="${MOODLE_DBPASS}" \
        --fullname="${MOODLE_SITE_FULLNAME:-Moodle Dev}" \
        --shortname="${MOODLE_SITE_SHORTNAME:-moodle}" \
        --adminuser="${MOODLE_ADMIN_USER:-admin}" \
        --adminpass="${MOODLE_ADMIN_PASS:-adminpass}" \
        --adminemail="${MOODLE_ADMIN_EMAIL:-admin@example.com}" \
        --agree-license \
        --non-interactive \
        "${extra[@]}"

    chown www-data:www-data "${CONFIG}"
    chmod 0644 "${CONFIG}"
    needs_purge=1
    log "Moodle installed."
}

# ── 4. Site configuration kept in sync with the environment ──────────────────
apply_wwwroot() {
    local current
    current="$(sed -nE "s/^[[:space:]]*\\\$CFG->wwwroot[[:space:]]*=[[:space:]]*'([^']*)'.*/\1/p" \
        "${CONFIG}" | head -1)"
    if [ -n "${MOODLE_WWWROOT:-}" ] && [ "${current}" != "${MOODLE_WWWROOT}" ]; then
        log "Updating wwwroot: ${current} → ${MOODLE_WWWROOT}"
        sed -i -E "s|^([[:space:]]*\\\$CFG->wwwroot[[:space:]]*=[[:space:]]*).*|\1'${MOODLE_WWWROOT}';|" \
            "${CONFIG}"
        needs_purge=1
    fi
}

# A single managed block, regenerated on every start, so .env is the source of
# truth for developer settings. Anything you add outside the markers is kept.
managed_block() {
    echo '// >>> ifthenpay-dev managed block — regenerated on container start, do not edit >>>'
    echo "\$CFG->phpunit_prefix = 'phpu_';"
    echo "\$CFG->phpunit_dataroot = '${PHPUNIT_DATAROOT}';"
    if [ "${MOODLE_DEV_MODE}" = "1" ]; then
        echo '$CFG->debug = 32767;              // DEVELOPER level.'
        echo '$CFG->debugdisplay = 1;'
        echo '$CFG->cachejs = false;            // Serve AMD sources without a rebuild.'
        echo '$CFG->cachetemplates = false;'
        echo '$CFG->langstringcache = false;    // Pick up lang file edits immediately.'
        echo '$CFG->noemailever = true;         // Never send real mail from a dev site.'
        echo '$CFG->passwordpolicy = false;'
    fi
    echo '// <<< ifthenpay-dev managed block <<<'
}

apply_managed_block() {
    local block tmp
    block="$(mktemp)"; tmp="$(mktemp)"
    managed_block > "${block}"

    awk -v blockfile="${block}" '
        /^\/\/ >>> ifthenpay-dev managed block/ { inblock = 1 }
        inblock {
            if (/^\/\/ <<< ifthenpay-dev managed block/) { inblock = 0 }
            next
        }
        /lib\/setup\.php/ && !inserted {
            while ((getline line < blockfile) > 0) { print line }
            close(blockfile)
            print ""
            inserted = 1
        }
        { print }
    ' "${CONFIG}" > "${tmp}"

    if grep -q 'ifthenpay-dev managed block' "${tmp}"; then
        if ! cmp -s "${CONFIG}" "${tmp}"; then
            cat "${tmp}" > "${CONFIG}"
            log "Developer settings applied (MOODLE_DEV_MODE=${MOODLE_DEV_MODE})."
            needs_purge=1
        fi
    else
        warn "Could not locate the lib/setup.php require in config.php — developer settings skipped."
    fi
    rm -f "${block}" "${tmp}"
}

# ── 5./6. Upgrades and plugin configuration ──────────────────────────────────
run_upgrade() {
    [ "${MOODLE_AUTO_UPGRADE}" = "1" ] || { log "Auto upgrade disabled."; return; }
    log "Checking for pending upgrades ..."
    if moodle_cli upgrade.php --non-interactive; then
        log "Core and plugins are up to date."
    else
        warn "admin/cli/upgrade.php failed — visit ${MOODLE_WWWROOT}/admin/index.php to finish it."
    fi
}

configure_plugin() {
    if [ "${PLUGIN_AUTO_ENABLE:-1}" != "1" ]; then
        log "Automatic gateway enabling disabled (PLUGIN_AUTO_ENABLE=0)."
        return
    fi
    if ! as_web php "${DEV_SETUP}"; then
        warn "Could not enable the ifthenpay gateway automatically."
    fi
}

purge_caches() {
    [ "${needs_purge}" = "1" ] || return 0
    log "Purging caches ..."
    moodle_cli purge_caches.php >/dev/null || warn "Cache purge failed."
}

# ── Run ──────────────────────────────────────────────────────────────────────
configure_xdebug
fix_apache_error_log
sync_core
ensure_dirs
wait_for_db

if [ -f "${CONFIG}" ]; then
    log "Existing config.php found."
else
    install_moodle
fi

apply_wwwroot
apply_managed_block
run_upgrade
configure_plugin
purge_caches

touch "${READY_FLAG}"
log "Ready → ${MOODLE_WWWROOT} (admin: ${MOODLE_ADMIN_USER:-admin} / ${MOODLE_ADMIN_PASS:-adminpass})"

exec "$@"
