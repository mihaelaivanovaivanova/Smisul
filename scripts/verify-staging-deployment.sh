#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
failures=0
fail() { echo "Safety failure [$1]: $2" >&2; failures=$((failures + 1)); }

if [[ ! -d backend || ! -d frontend || ! -f deployment/public_html/laravel.php ]]; then
  fail 'layout' 'Laravel source layout requires sibling backend/, frontend/, and deployment/public_html/ paths.'
fi
[[ ! -d backend/root && ! -d root/backend && ! -d root/Backend ]] || fail 'layout' 'Public and private application directories must not be nested.'

tracked="$(git ls-files)"
for path in backend/.env Backend/.env backend/config.php Backend/config.php backend/config.staging.php Backend/config.staging.php; do
  grep -Fqx "$path" <<<"$tracked" && fail 'server-config' "$path is tracked; the real server config must remain server-only."
done
while IFS= read -r path; do
  [[ -z "$path" ]] && continue
  [[ "$path" == *.example ]] || fail 'environment' "$path is a tracked environment file."
done < <(grep -Ei '(^|/)\.env($|\.)' <<<"$tracked" || true)

while IFS= read -r path; do
  [[ -z "$path" ]] && continue
  case "$path" in
    backend/tests/Fixtures/icard/test_private_key.pem|backend/tests/Fixtures/icard/test_public_key.pem) ;;
    *) fail 'payment-files' "$path is a tracked key or certificate file." ;;
  esac
done < <(grep -Ei '\.(pem|key|p12|pfx|crt|cer)$' <<<"$tracked" || true)

workflow=.github/workflows/deploy-staging.yml
[[ -f "$workflow" ]] || fail 'workflow' "$workflow is missing."
if [[ -f "$workflow" ]]; then
  grep -Eq '^      - Deployment$' "$workflow" || fail 'workflow-branch' "$workflow does not target Deployment exactly."
  if ! awk '
    /^    branches:$/ { in_branches=1; next }
    in_branches && /^  workflow_dispatch:/ { in_branches=0 }
    in_branches && /^[[:space:]]+- / && $0 !~ /^      - Deployment$/ { bad=1 }
    END { exit bad }
  ' "$workflow"; then
    fail 'workflow-branch' "$workflow contains an unexpected branch trigger."
  fi
  grep -Eq '^      name: staging$' "$workflow" || fail 'environment' "$workflow does not use the staging environment."
  grep -Eq '^permissions:$' "$workflow" && grep -Eq '^  contents: read$' "$workflow" || fail 'permissions' "$workflow permissions are not read-only."
fi

deployment_files=(.github/workflows scripts/deploy-staging-ftps.sh)
delete_word="--del""ete"
delete_first_word="--del""ete-first"
clean_word="dangerous-clean""-slate"
rsync_delete_word="rsync --del""ete"
for word in "$delete_word" "$delete_first_word" "$clean_word" "$rsync_delete_word"; do
  if grep -RInF --exclude=verify-staging-deployment.sh -- "$word" "${deployment_files[@]}" >/dev/null 2>&1; then
    fail 'remote-delete' "A deployment command contains forbidden remote-clean behavior: $word"
  fi
done

install_exec='php[[:space:]]+.*install\.php|curl[^#]*(/|=)install\.php|wget[^#]*(/|=)install\.php'
migration_exec='artisan[[:space:]]+(migrate|db:seed)'
if grep -REin --exclude=verify-staging-deployment.sh -- "$install_exec" "${deployment_files[@]}" >/dev/null 2>&1; then
  fail 'installer' 'A workflow or deployment script executes install.php.'
fi
if grep -REin --exclude=verify-staging-deployment.sh -- "$migration_exec" "${deployment_files[@]}" >/dev/null 2>&1; then
  fail 'database' 'A workflow or deployment script runs automatic database changes.'
fi
fresh_word='migrate:'"fresh"
if grep -RInF --exclude=verify-staging-deployment.sh -- "$fresh_word" "${deployment_files[@]}" >/dev/null 2>&1; then
  fail 'database' 'A workflow or deployment script runs a destructive migration.'
fi

if grep -REin --exclude=verify-staging-deployment.sh -- 'mirror[^$]*(/smisul\.bg/(root|Backend|backend))' "${deployment_files[@]}" >/dev/null 2>&1; then
  fail 'production-target' 'A deployment command hardcodes a production directory.'
fi
while IFS= read -r line; do
  [[ -z "$line" ]] && continue
  grep -Fq -- '--exclude-glob=.well-known' <<<"$line" || fail 'apple-pay' 'A deployment mirror mentions .well-known without an explicit exclusion.'
done < <(grep -Rih --exclude=verify-staging-deployment.sh -E 'mirror .*\.well-known' "${deployment_files[@]}" || true)

for example in backend/config.example.php backend/config.staging.example.php; do
  [[ -f "$example" ]] || { fail 'config-example' "$example is missing."; continue; }
  grep -Eq 'your_|change_this|example\.com|testing-admin' "$example" || fail 'config-example' "$example does not contain obvious placeholders."
done

candidate_files="$(git ls-files --cached --others --exclude-standard -- . ':(exclude)Smisul/**' | grep -Ev '^(\.git/|backend/tests/Fixtures/icard/)' || true)"
while IFS= read -r path; do
  [[ -z "$path" || ! -f "$path" ]] && continue
  case "$path" in
    *.md|*.example|*.example.php|*.yml|*.yaml|*.sh) ;;
    *)
      if grep -Eq -- '-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----' "$path"; then
        fail 'private-key' "$path contains private-key material."
      fi
      ;;
  esac
done <<<"$candidate_files"

for path in backend/.env backend/config.php Backend/.env Backend/config.php; do
  git check-ignore -q -- "$path" || fail 'ignore' "$path is not ignored."
done

if [[ "${1:-}" == '--build' ]]; then
  [[ -d .build/staging-root && -d .build/staging-backend ]] || fail 'build' 'Isolated build directories are missing.'
  for path in \
    .build/staging-root/.well-known \
    .build/staging-root/install.php \
    .build/staging-backend/.env \
    .build/staging-backend/config.php \
    .build/staging-backend/storage/app/public \
    .build/staging-backend/storage/app/private \
    .build/staging-backend/storage/icard \
    .build/staging-backend/storage/logs \
    .build/staging-backend/storage/framework/sessions; do
    [[ ! -e "$path" ]] || fail 'protected-build-path' "$path is present in the staging build."
  done
  [[ -f .build/staging-root/robots.txt ]] || fail 'robots' 'Staging robots.txt is missing.'
  grep -Fq "dirname(__DIR__).'/Backend'" .build/staging-root/laravel.php || fail 'layout' 'Staging launcher does not point to sibling Backend.'
  [[ -f .build/staging-backend/vendor/autoload.php ]] || fail 'composer' 'Production Composer vendor files are missing from the staging build.'
fi

if (( failures > 0 )); then
  echo "$failures staging deployment safety check(s) failed." >&2
  exit 1
fi

echo 'Staging deployment safety verification passed: Deployment-only trigger, no remote deletion, no installer execution, no automatic database changes, protected configs/uploads/iCard paths excluded.'
