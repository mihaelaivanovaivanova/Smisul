#!/usr/bin/env bash
set -Eeuo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

[[ -d frontend/dist ]] || { echo 'Safety failure: frontend/dist is missing; build the frontend first.' >&2; exit 1; }
[[ -d backend ]] || { echo 'Safety failure: backend source directory is missing.' >&2; exit 1; }

# Build from an explicit production allowlist. Never copy the whole repository
# and try to remove unsafe files afterwards: an omitted exclusion would then be
# uploaded to hosting.
backend_directories=(app bootstrap config database public resources routes vendor)
backend_files=(artisan composer.json composer.lock)

for path in "${backend_directories[@]}" "${backend_files[@]}"; do
  [[ -e "backend/$path" ]] || {
    echo "Safety failure: required production path is missing: backend/$path" >&2
    exit 1
  }
done
[[ -f backend/vendor/autoload.php ]] || {
  echo 'Safety failure: backend/vendor/autoload.php is missing; run Composer production install first.' >&2
  exit 1
}

rm -rf -- .build
mkdir -p .build/staging-root .build/staging-backend

# Public hosting root: only the compiled frontend and the reviewed Laravel
# launcher/routing files are deployable.
cp -a frontend/dist/. .build/staging-root/
cp deployment/public_html/.htaccess .build/staging-root/.htaccess
cp deployment/public_html/laravel.php .build/staging-root/laravel.php

# Private Laravel directory: only runtime code and Composer production
# dependencies. Project tests, documentation, development tooling and local
# storage contents are intentionally impossible to enter this build.
for path in "${backend_directories[@]}"; do
  cp -a "backend/$path" ".build/staging-backend/$path"
done
for path in "${backend_files[@]}"; do
  cp "backend/$path" ".build/staging-backend/$path"
done

# Local/generated web artifacts must never be promoted even if they happen to
# exist inside a developer checkout.
rm -rf -- \
  .build/staging-root/.well-known \
  .build/staging-root/uploads \
  .build/staging-root/assets/uploads \
  .build/staging-root/storage \
  .build/staging-backend/database/factories \
  .build/staging-backend/public/hot \
  .build/staging-backend/public/storage

# Development-only metadata outside Composer packages is not executable
# application content. Composer's own package contents remain untouched.
find .build/staging-root .build/staging-backend \
  -path '.build/staging-backend/vendor' -prune -o \
  -type f \( -name 'README.md' -o -name '.gitignore' -o -name '.gitkeep' \) \
  -exec rm -f -- {} +

find .build -type f \( \
  -name '.env' -o -name '.env.*' -o -name '*.zip' -o -name '*.sql' \
  -o -name '*.sqlite' -o -name '*.log' \
\) -delete

# The confirmed testing private directory is lowercase backend on Linux.
sed -i "s#dirname(__DIR__)\.'/Backend'#dirname(__DIR__)\.'/backend'#" .build/staging-root/laravel.php
grep -Fq "dirname(__DIR__).'/backend'" .build/staging-root/laravel.php || {
  echo 'Safety failure: staging launcher does not point to sibling backend.' >&2
  exit 1
}

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

root_files="$(find .build/staging-root -type f | wc -l | tr -d '[:space:]')"
backend_files_total="$(find .build/staging-backend -type f | wc -l | tr -d '[:space:]')"
vendor_files="$(find .build/staging-backend/vendor -type f | wc -l | tr -d '[:space:]')"
application_files="$((backend_files_total - vendor_files))"
total_files="$((root_files + backend_files_total))"

cat > .build/staging-manifest.txt <<MANIFEST
Staging deployment manifest
public root files: $root_files
Laravel application files (without vendor): $application_files
Composer vendor files: $vendor_files
Total files in upload build: $total_files
MANIFEST

{
  find .build/staging-root -type f | sed 's#^\.build/staging-root/#root/#'
  find .build/staging-backend -type f | sed 's#^\.build/staging-backend/#backend/#'
} | LC_ALL=C sort > .build/staging-file-list.txt

cat .build/staging-manifest.txt
echo 'Staging build prepared from the production allowlist.'
