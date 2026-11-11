#!/usr/bin/env bash
# Runs once, right after the dev container is created.
#   .env bootstrap → ownership repair → composer install → npm install
set -euo pipefail

REPO="${PLUGIN_REPO:-/workspace/plugin}"
cd "${REPO}"

cyan=$'\033[1;36m'; green=$'\033[1;32m'; yellow=$'\033[1;33m'; reset=$'\033[0m'
step() { printf '\n%s▶ %s%s\n' "${cyan}" "$*" "${reset}"; }
note() { printf '  %s\n' "$*"; }

step "Local configuration"
if [ -f .env ]; then
    note ".env already present — leaving it untouched."
else
    cp .env.example .env
    note "Created .env from .env.example. Edit it and run 'docker compose up -d' to apply."
fi

step "Ownership and permissions"
uid="$(id -u)"; gid="$(id -g)"
if [ ! -w "${REPO}" ] || [ ! -w "${REPO}/src" ] || [ "$(stat -c '%u' "${REPO}/src")" != "${uid}" ]; then
    note "${yellow}Repository files are not owned by this container's user (${uid}:${gid}).${reset}"
    note "This usually means your host user's id isn't 1000. Not fixing it automatically —"
    note "that would chown your actual clone on disk, which can lock out host-side tools"
    note "(an editor or git run outside the container) if the guess is wrong."
    note "Set ${yellow}APP_UID=$(id -u)${reset} and ${yellow}APP_GID=$(id -g)${reset} in .env, then"
    note "F1 → 'Dev Containers: Rebuild Container'."
else
    note "Everything is owned by $(id -un) — nothing to do."
fi
git config --global --add safe.directory "${REPO}" 2>/dev/null || true

step "PHP dependencies (composer)"
XDEBUG_MODE=off composer install --no-interaction --no-progress

step "JavaScript dependencies (npm)"
npm install --no-fund --no-audit

step "Moodle core dev dependencies (composer)"
# phpstan-moodle (mdl analyse) and PHPUnit (mdl phpunit-init) both need Moodle
# core's own vendor/, separate from the plugin's. A few seconds, once.
dirroot="${MOODLE_DIRROOT:-/var/www/html}"
(cd "${dirroot}" && XDEBUG_MODE=off composer install --no-interaction --no-progress)

step "Ready"
note "Moodle core is mounted at /var/www/html — Moodle CLI works from this terminal."
note "Type ${yellow}mdl help${reset} to see the available commands."
