#!/usr/bin/env bash
# Runs on every dev container start. The container only starts once the app
# container reports healthy (docker-compose depends_on), which means Moodle is
# installed, upgraded and configured by then.
set -euo pipefail

REPO="${PLUGIN_REPO:-/workspace/plugin}"
DIRROOT="${MOODLE_DIRROOT:-/var/www/html}"

yellow=$'\033[1;33m'; reset=$'\033[0m'
warn() { printf '%s%s%s\n' "${yellow}" "$*" "${reset}" >&2; }

# Guard rail: editing the plugin must always be possible from here.
if [ ! -w "${REPO}/src" ]; then
    warn "⚠  ${REPO}/src is not writable by $(id -un) — saving files will fail."
    warn "   Fix with: sudo chown -R $(id -u):$(id -g) ${REPO}"
    warn "   Permanent fix: set APP_UID/APP_GID in .env to your host's id -u/id -g and rebuild."
fi

if [ ! -f "${DIRROOT}/config.php" ]; then
    warn "⚠  Moodle is not installed yet. Check the app container on the host:"
    warn "   docker compose logs -f ifthenpay-app"
    exit 0
fi

exec "${REPO}/.devcontainer/bin/mdl" info
