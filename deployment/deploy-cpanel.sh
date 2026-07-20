#!/usr/bin/env bash

set -Eeuo pipefail

# cPanel runs this script from its managed Git checkout. The checkout stays
# outside the web root; only production-ready files are synchronized below.
REPOSITORY_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEPLOY_ROOT="${SMISUL_DEPLOY_ROOT:-${HOME}/smisul.bg}"
PUBLIC_ROOT="${DEPLOY_ROOT}/root"
BACKEND_ROOT="${DEPLOY_ROOT}/backend"
FRONTEND_ARTIFACT="${REPOSITORY_ROOT}/deployment/artifacts/frontend"

log() {
    printf '[Smisul deploy] %s\n' "$1"
}

fail() {
    printf '[Smisul deploy] ERROR: %s\n' "$1" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Required command '$1' is not available."
}

require_command git
require_command rsync
require_command install

PHP_BIN="${SMISUL_PHP_BIN:-php}"
command -v "${PHP_BIN}" >/dev/null 2>&1 || fail "PHP CLI is not available. Set SMISUL_PHP_BIN to the PHP 8.2 executable."
"${PHP_BIN}" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' \
    || fail "PHP 8.2 or newer is required for deployment."

if command -v composer >/dev/null 2>&1; then
    COMPOSER=(composer)
elif [[ -f "${HOME}/composer.phar" ]]; then
    COMPOSER=("${PHP_BIN}" "${HOME}/composer.phar")
else
    fail "Composer was not found. Install it as ${HOME}/composer.phar before the first deployment."
fi

CURRENT_BRANCH="$(git -C "${REPOSITORY_ROOT}" branch --show-current)"
if [[ "${CURRENT_BRANCH}" != "NewFunnelLayout" ]]; then
    fail "The checked-out branch is '${CURRENT_BRANCH:-detached HEAD}', expected 'NewFunnelLayout'. Select NewFunnelLayout in cPanel Git Version Control before deploying."
fi

[[ -f "${REPOSITORY_ROOT}/frontend/package-lock.json" ]] || fail "frontend/package-lock.json is missing."
[[ -f "${REPOSITORY_ROOT}/backend/composer.lock" ]] || fail "backend/composer.lock is missing."
[[ -f "${FRONTEND_ARTIFACT}/index.html" ]] || fail "The committed frontend artifact is missing. Run scripts/prepare-cpanel-deploy.ps1 locally, commit the result, and push it."

log "Installing production PHP dependencies..."
"${COMPOSER[@]}" install \
    --working-dir="${REPOSITORY_ROOT}/backend" \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

[[ -f "${REPOSITORY_ROOT}/backend/vendor/autoload.php" ]] || fail "Composer did not create vendor/autoload.php."

log "Creating the SuperHosting directory structure..."
mkdir -p \
    "${PUBLIC_ROOT}" \
    "${BACKEND_ROOT}" \
    "${BACKEND_ROOT}/storage/app/public" \
    "${BACKEND_ROOT}/storage/app/private" \
    "${BACKEND_ROOT}/storage/framework/cache/data" \
    "${BACKEND_ROOT}/storage/framework/sessions" \
    "${BACKEND_ROOT}/storage/framework/testing" \
    "${BACKEND_ROOT}/storage/framework/views" \
    "${BACKEND_ROOT}/storage/logs" \
    "${BACKEND_ROOT}/bootstrap/cache"

log "Publishing frontend files to ${PUBLIC_ROOT}..."
rsync -a --delete \
    --exclude='.well-known/' \
    --exclude='.user.ini' \
    --exclude='php.ini' \
    --exclude='storage' \
    "${FRONTEND_ARTIFACT}/" \
    "${PUBLIC_ROOT}/"

install -m 0644 "${REPOSITORY_ROOT}/deployment/public_html/.htaccess" "${PUBLIC_ROOT}/.htaccess"
install -m 0644 "${REPOSITORY_ROOT}/deployment/public_html/laravel.php" "${PUBLIC_ROOT}/laravel.php"

log "Publishing Laravel files to ${BACKEND_ROOT}..."
rsync -a --delete \
    --exclude='.env' \
    --exclude='.user.ini' \
    --exclude='php.ini' \
    --exclude='install-config.php' \
    --exclude='storage/' \
    --exclude='database/*.sqlite' \
    --exclude='tests/' \
    --exclude='phpunit.xml' \
    --exclude='phpstan.neon' \
    "${REPOSITORY_ROOT}/backend/" \
    "${BACKEND_ROOT}/"

install -m 0644 "${REPOSITORY_ROOT}/deployment/backend.env.production.example" "${BACKEND_ROOT}/.env.example"
install -m 0644 "${REPOSITORY_ROOT}/deployment/install-config.php" "${BACKEND_ROOT}/install-config.example.php"

chmod -R u+rwX,g+rwX "${BACKEND_ROOT}/storage" "${BACKEND_ROOT}/bootstrap/cache"

if [[ ! -e "${PUBLIC_ROOT}/storage" ]]; then
    ln -s "${BACKEND_ROOT}/storage/app/public" "${PUBLIC_ROOT}/storage" \
        || log "Could not create the public storage symlink automatically."
elif [[ ! -L "${PUBLIC_ROOT}/storage" ]]; then
    log "${PUBLIC_ROOT}/storage exists but is not a symlink; it was preserved."
fi

if [[ -f "${BACKEND_ROOT}/.env" ]]; then
    log "Running pending Laravel migrations and rebuilding the production cache..."
    (
        cd "${BACKEND_ROOT}"
        "${PHP_BIN}" artisan migrate --force
        "${PHP_BIN}" artisan optimize:clear
        "${PHP_BIN}" artisan config:cache
    )
    rm -f "${PUBLIC_ROOT}/install.php"
else
    install -m 0644 "${REPOSITORY_ROOT}/deployment/public_html/install.php" "${PUBLIC_ROOT}/install.php"
    log "First deployment detected: .env is not present."
    log "Copy install-config.example.php to install-config.php, fill it in, then open /install.php."
fi

log "Deployment completed successfully from branch NewFunnelLayout."
