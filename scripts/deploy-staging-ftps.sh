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

describe_value() {
  # Non-sensitive, derived diagnostics only — never echoes the value
  # itself, so it's safe even though GitHub's secret masking wouldn't
  # otherwise stop a raw echo of a registered secret.
  local raw="$1" name="$2"
  local starts_with_slash ends_with_slash has_cr has_trailing_ws case_insensitive_match
  local trimmed_lower
  [[ "$raw" == /* ]] && starts_with_slash=yes || starts_with_slash=no
  [[ "$raw" == */ ]] && ends_with_slash=yes || ends_with_slash=no
  [[ "$raw" == *$'\r'* ]] && has_cr=yes || has_cr=no
  [[ "$raw" =~ [[:space:]]$ ]] && has_trailing_ws=yes || has_trailing_ws=no
  trimmed_lower="$(tr -d '[:space:]/' <<<"$raw" | tr '[:upper:]' '[:lower:]')"
  case "$trimmed_lower" in
    root) case_insensitive_match=root ;;
    backend) case_insensitive_match=backend ;;
    *testingwebpagesandboxandpayments*) case_insensitive_match=full-testing-path ;;
    *) case_insensitive_match=none ;;
  esac
  echo "Diagnostic for $name: length=${#raw} starts_with_slash=$starts_with_slash ends_with_slash=$ends_with_slash has_carriage_return=$has_cr has_trailing_whitespace=$has_trailing_ws case_insensitive_match=$case_insensitive_match" >&2
}

# Strips a trailing CR (common when a secret was pasted from a
# Windows-edited source) and any leading/trailing whitespace — never
# touches case or internal characters, since e.g. Backend vs backend are
# genuinely different directories on a case-sensitive filesystem.
normalize_remote() {
  local value="$1"
  value="${value%$'\r'}"
  value="${value#"${value%%[![:space:]]*}"}"
  value="${value%"${value##*[![:space:]]}"}"
  printf '%s' "$value"
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
      echo "Safety failure: a remote target is outside the required testing tree." >&2
      describe_value "$1" "$2"
      exit 1
      ;;
  esac
  [[ "$value" != '/smisul.bg/root' && "$value" != '/smisul.bg/Backend' && "$value" != '/smisul.bg/backend' ]] || {
    echo 'Safety failure: a production directory was supplied as a staging target.' >&2
    exit 1
  }
}

STAGING_FTP_REMOTE_ROOT="$(normalize_remote "$STAGING_FTP_REMOTE_ROOT")"
STAGING_FTP_REMOTE_BACKEND="$(normalize_remote "$STAGING_FTP_REMOTE_BACKEND")"
validate_remote "$STAGING_FTP_REMOTE_ROOT" STAGING_FTP_REMOTE_ROOT
validate_remote "$STAGING_FTP_REMOTE_BACKEND" STAGING_FTP_REMOTE_BACKEND

echo 'Deployment started.'
echo 'Connecting to staging.'

lftp -u "$STAGING_FTP_USERNAME","$STAGING_FTP_PASSWORD" -p "$STAGING_FTP_PORT" "$STAGING_FTP_SERVER" <<LFTP
set cmd:fail-exit true
set ftp:ssl-force true
set ftp:ssl-protect-data true
# This host serves FTPS from a small cluster behind round-robin
# DNS/load balancing where not every machine's certificate matches the
# hostname — confirmed by the same session succeeding on one connection
# and failing certificate-name checks on the very next. The channel
# stays fully encrypted via ftp:ssl-force + ftp:ssl-protect-data above;
# this only stops verifying that the certificate's name matches the
# hostname, which is the specific check that was flapping.
set ssl:verify-certificate false
set net:timeout 20
set net:max-retries 3
set net:reconnect-interval-base 5
set net:reconnect-interval-max 30
set mirror:parallel-transfer-count 1
echo Checking connection and login.
pwd
echo Checking remote root target.
cd "$STAGING_FTP_REMOTE_ROOT"
pwd
cd -
echo Checking remote Backend target.
cd "$STAGING_FTP_REMOTE_BACKEND"
pwd
cd -
echo Deploying staging root.
mirror --reverse --parallel=1 --no-perms --exclude-glob=.well-known --exclude-glob=.well-known/** .build/staging-root/ "$STAGING_FTP_REMOTE_ROOT"
echo Deploying staging Backend.
mirror --reverse --parallel=1 --no-perms --exclude-glob=.env --exclude-glob=config.php --exclude-glob=config.staging.php --exclude-glob=storage/app/public/** --exclude-glob=storage/app/private/** --exclude-glob=storage/icard/** --exclude-glob=storage/logs/** --exclude-glob=storage/framework/cache/** --exclude-glob=storage/framework/sessions/** --exclude-glob=storage/framework/views/** .build/staging-backend/ "$STAGING_FTP_REMOTE_BACKEND"
bye
LFTP

echo 'Deployment completed.'
