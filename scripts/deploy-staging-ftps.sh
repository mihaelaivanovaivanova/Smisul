#!/usr/bin/env bash
set -Eeuo pipefail

required=(
  STAGING_FTP_SERVER STAGING_FTP_PORT STAGING_FTP_USERNAME
  STAGING_FTP_PASSWORD STAGING_FTP_REMOTE_ROOT STAGING_FTP_REMOTE_BACKEND
)
for name in "${required[@]}"; do
  [[ -n "${!name:-}" ]] || { echo "Safety failure: required staging variable is missing: $name" >&2; exit 1; }
done

[[ -d .build/staging-root && -d .build/staging-backend ]] || {
  echo 'Safety failure: isolated staging build directories are missing.' >&2
  exit 1
}

validate_remote() {
  local value="${1%/}"
  case "$value" in
    */testing/webpage/sandboxandpayments/*) ;;
    # The staging FTP account can also be jailed directly inside
    # .../sandboxandpayments/ itself (confirmed for this account), in
    # which case the visible remote root is just /root/ or /Backend/ —
    # that string can never contain the full testing-tree path above,
    # so it's allowed explicitly here instead.
    /root|/Backend) ;;
    *)
      echo 'Safety failure: a remote target is outside the required testing tree.' >&2
      exit 1
      ;;
  esac
  [[ "$value" != '/smisul.bg/root' && "$value" != '/smisul.bg/Backend' && "$value" != '/smisul.bg/backend' ]] || {
    echo 'Safety failure: a production directory was supplied as a staging target.' >&2
    exit 1
  }
}
validate_remote "$STAGING_FTP_REMOTE_ROOT"
validate_remote "$STAGING_FTP_REMOTE_BACKEND"

echo 'Deployment started.'
echo 'Connecting to staging.'

lftp -u "$STAGING_FTP_USERNAME","$STAGING_FTP_PASSWORD" -p "$STAGING_FTP_PORT" "$STAGING_FTP_SERVER" <<LFTP
set cmd:fail-exit true
set ftp:ssl-force true
set ftp:ssl-protect-data true
set ssl:verify-certificate true
set net:timeout 20
set net:max-retries 3
set net:reconnect-interval-base 5
set net:reconnect-interval-max 30
set mirror:parallel-transfer-count 3
echo Deploying staging root.
mirror --reverse --parallel=3 --no-perms --exclude-glob=.well-known --exclude-glob=.well-known/** .build/staging-root/ "$STAGING_FTP_REMOTE_ROOT"
echo Deploying staging Backend.
mirror --reverse --parallel=3 --no-perms --exclude-glob=.env --exclude-glob=config.php --exclude-glob=config.staging.php --exclude-glob=storage/app/public/** --exclude-glob=storage/app/private/** --exclude-glob=storage/icard/** --exclude-glob=storage/logs/** --exclude-glob=storage/framework/cache/** --exclude-glob=storage/framework/sessions/** --exclude-glob=storage/framework/views/** .build/staging-backend/ "$STAGING_FTP_REMOTE_BACKEND"
bye
LFTP

echo 'Deployment completed.'
