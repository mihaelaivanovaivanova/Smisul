#!/usr/bin/env bash
set -Eeuo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

rm -rf -- .build
mkdir -p .build/staging-root .build/staging-backend

if [[ -d root && -d backend ]]; then
  cp -a root/. .build/staging-root/
  cp -a backend/. .build/staging-backend/
elif [[ -d root && -d Backend ]]; then
  cp -a root/. .build/staging-root/
  cp -a Backend/. .build/staging-backend/
else
  [[ -d frontend/dist ]] || { echo 'Safety failure: frontend/dist is missing; build the frontend first.' >&2; exit 1; }
  [[ -d backend ]] || { echo 'Safety failure: backend source directory is missing.' >&2; exit 1; }
  cp -a frontend/dist/. .build/staging-root/
  cp -a backend/. .build/staging-backend/
  cp deployment/public_html/.htaccess .build/staging-root/.htaccess
  cp deployment/public_html/laravel.php .build/staging-root/laravel.php
fi

remove_from_both() {
  local target
  for target in "$@"; do
    rm -rf -- ".build/staging-root/$target" ".build/staging-backend/$target"
  done
}

remove_from_both .git .github scripts tests .env .env.backup .env.production .env.staging .env.testing \
  .phpunit.result.cache phpunit.xml phpstan.neon README.md logs cache tmp database/backups

rm -rf -- \
  .build/staging-root/.well-known \
  .build/staging-root/install.php \
  .build/staging-root/uploads \
  .build/staging-root/assets/uploads \
  .build/staging-root/storage/logs \
  .build/staging-backend/config.php \
  .build/staging-backend/config.staging.php \
  .build/staging-backend/config.example.php \
  .build/staging-backend/config.staging.example.php \
  .build/staging-backend/install-config.php \
  .build/staging-backend/install-config.example.php \
  .build/staging-backend/install-config.staging.php \
  .build/staging-backend/storage/app/public \
  .build/staging-backend/storage/app/private \
  .build/staging-backend/storage/icard \
  .build/staging-backend/storage/logs \
  .build/staging-backend/storage/framework/cache \
  .build/staging-backend/storage/framework/sessions \
  .build/staging-backend/storage/framework/views

find .build -type f \( \
  -name '.env' -o -name '.env.*' -o -name '*.zip' -o -name '*.sql' \
  -o -name '*.sqlite' -o -name '*.log' \
\) -delete

# The confirmed testing private directory is lowercase backend on Linux.
if [[ -f .build/staging-root/laravel.php ]]; then
  sed -i "s#dirname(__DIR__)\.'/Backend'#dirname(__DIR__)\.'/backend'#" .build/staging-root/laravel.php
  grep -Fq "dirname(__DIR__).'/backend'" .build/staging-root/laravel.php || {
    echo 'Safety failure: staging launcher does not point to sibling backend.' >&2
    exit 1
  }
fi

# The staging installer is opt-in and one-time. Enable it with the GitHub
# Environment variable STAGING_INCLUDE_INSTALLER=true, complete installation,
# then remove/disable the variable so later deployments cannot re-upload it.
if [[ "${STAGING_INCLUDE_INSTALLER:-false}" == 'true' ]]; then
  cp deployment/staging/install.php .build/staging-root/install.php
  echo 'One-time staging installer included.'
fi

cat > .build/staging-root/robots.txt <<'ROBOTS'
User-agent: *
Disallow: /
ROBOTS

cat >> .build/staging-root/.htaccess <<'HTACCESS'

# Staging-only indexing protection, added to the isolated build.
<IfModule mod_headers.c>
    Header always set X-Robots-Tag "noindex, nofollow, noarchive"
</IfModule>
HTACCESS

echo 'Staging build prepared.'
