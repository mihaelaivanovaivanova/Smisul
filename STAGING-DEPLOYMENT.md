# Smisul testing deployment

This repository configures **testing only**. It does not configure, target, clean, or otherwise modify production.

## Filesystem and branch boundaries

Production remains unchanged:

```text
smisul.bg/
├── Backend/
└── root/                 # production document root
```

Testing must be created separately:

```text
smisul.bg/
└── testing/
    └── webpage/
        └── sandboxandpayments/
            ├── Backend/  # private Laravel application
            └── root/     # testing document root
```

The public URL is `https://sandboxandpayments.smisul.bg`. In SuperHosting/cPanel, create that subdomain and set its document root to:

```text
smisul.bg/testing/webpage/sandboxandpayments/root/
```

Do not change production DNS or the `smisul.bg/root/` document root. Never expose the testing `Backend/` directory.

Development continues on `Contributor/Vlado`. The exact branch `Deployment` is testing deployment only:

```text
Contributor/Vlado → merge into Deployment → push Deployment
→ GitHub Actions → testing server
```

The repository source is Laravel and currently uses `frontend/`, `backend/`, and `deployment/public_html/`. The workflow builds those sources into isolated `.build/staging-root/` and `.build/staging-backend/` directories. Those two build directories are uploaded as remote sibling `root/` and `Backend/`; source directories are never flattened, moved, or uploaded directly.

To publish a reviewed version:

```bash
git switch Deployment
git merge Contributor/Vlado
git push origin Deployment
```

Review runs at **GitHub repository → Actions → Deploy Smisul Testing**. The push trigger always works from `Deployment`. GitHub may not show the **Run workflow** button until the workflow definition also exists in the repository's default branch; this does not change the automatic trigger.

## GitHub environment and FTPS paths

Create a protected GitHub Environment named exactly `staging`. Add these Environment secrets:

- `STAGING_FTP_SERVER`
- `STAGING_FTP_PORT` (normally `21`)
- `STAGING_FTP_USERNAME`
- `STAGING_FTP_PASSWORD`
- `STAGING_FTP_REMOTE_ROOT`
- `STAGING_FTP_REMOTE_BACKEND`

Typical values when the FTP account starts above `smisul.bg` are:

```text
STAGING_FTP_REMOTE_ROOT=/smisul.bg/testing/webpage/sandboxandpayments/root/
STAGING_FTP_REMOTE_BACKEND=/smisul.bg/testing/webpage/sandboxandpayments/Backend/
```

If the account starts inside `smisul.bg`, use `/testing/webpage/sandboxandpayments/root/` and `/testing/webpage/sandboxandpayments/Backend/`.

If the account is jailed directly inside `.../sandboxandpayments/` itself (this project's actual account), the visible remote root after login already **is** the testing tree, so the two sibling directories are simply:

```text
STAGING_FTP_REMOTE_ROOT=/root/
STAGING_FTP_REMOTE_BACKEND=/Backend/
```

Do not guess. Connect with FileZilla using the same account, note the initial remote directory shown after login, navigate to the testing tree, and copy the paths displayed for the two sibling directories. The deployment script accepts either the full `/.../testing/webpage/sandboxandpayments/...` form or the bare jailed-account `/root/`/`/Backend/` form, and rejects everything else, including any path that could resolve to the production `smisul.bg/root/` or `smisul.bg/Backend/`.

The workflow forces FTP over TLS, verifies the server certificate, uploads new/changed files, and never performs remote deletion. Remote-only files remain during normal deployments and rollbacks.

## First-time SuperHosting/cPanel setup

Complete these manual steps before expecting the testing site to work:

1. Create `sandboxandpayments.smisul.bg` and point only that subdomain to `smisul.bg/testing/webpage/sandboxandpayments/root/`. Add its DNS record if cPanel does not do so automatically. Prefer cPanel password protection in addition to the generated `robots.txt` and `X-Robots-Tag` header.
2. Create the `root/` and `Backend/` sibling directories under `smisul.bg/testing/webpage/sandboxandpayments/` if the FTP account cannot create them.
3. Select PHP 8.2 or a compatible newer release and enable `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `mbstring`, `openssl`, `pdo_mysql`, `session`, `tokenizer`, and `xml`.
4. Create a new testing MySQL database and a new testing database user in cPanel; grant that user only the testing database privileges. Do not copy or connect to the production database.
5. After the first file upload, manually create `smisul.bg/testing/webpage/sandboxandpayments/Backend/.env`. This is the exact runtime configuration used by Laravel. Start from the safe tracked `backend/.env.example`; do not use `config.example.php` as a runtime file and do not copy production `.env`.
6. In testing `.env`, set a new `APP_KEY`, `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=https://sandboxandpayments.smisul.bg`, the separate testing database credentials, a separate testing administrator, and non-production mail settings. Set `SESSION_COOKIE=SMISUL_TESTING_SESSION`, `SESSION_DOMAIN=` (empty, for a host-only cookie), `SESSION_SECURE_COOKIE=true`, and preferably `SESSION_SAME_SITE=lax`. This isolates carts and admin/auth sessions from production.
7. Set writable permissions as required by SuperHosting for `Backend/storage/` and `Backend/bootstrap/cache/`. If public media is used, create `root/storage` as a server-side link to `Backend/storage/app/public`; do not place that server link in Git.
8. Apply schema changes only after verifying that `.env` names the testing database. A qualified operator may run `php artisan migrate --force` manually from the testing `Backend/`. Never run a fresh migration, a database drop, production SQL import, seeders, or `install.php` as part of deployment.
9. Sign in with separate testing administrator credentials and configure only the iCard sandbox profile under **Settings → Payments**. Set testing callback/return URLs using `https://sandboxandpayments.smisul.bg`; never copy production iCard credentials, settings, keys, or certificates.

The example PHP files `backend/config.example.php` and `backend/config.staging.example.php` are safe structural references requested for deployment review. The inspected Laravel bootstrap does not load them. The real local/server runtime config path is `backend/.env` in source casing and `Backend/.env` in the required remote testing layout.

## Persistent files discovered in source

iCard configuration behavior is unchanged:

- `backend/app/Http/Controllers/Api/V1/Admin/ICardConfigurationController.php` handles admin submissions at `/api/v1/admin/payment-settings/icard/{environment}`.
- `backend/app/Services/Payments/ICardConfigurationService.php` validates/normalizes the submitted PEM text.
- Text settings and private/public PEM values are encrypted by Laravel model casts and stored in the `icard_configurations` database table. The admin flow does **not** write uploaded iCard files to disk.
- Legacy environment fallback paths are `backend/storage/icard/private_key.pem` and `backend/storage/icard/public_key.pem`; the whole remote `Backend/storage/icard/` path is protected.
- A legacy one-time runtime import may exist at `backend/storage/app/private/icard-import.php`; the whole remote `Backend/storage/app/private/` path is protected.
- Test-only fixtures `backend/tests/Fixtures/icard/test_private_key.pem` and `test_public_key.pem` are non-production test data and are excluded because all tests are excluded from the build.

The exact server-persistent paths detected or required by the framework are:

- `Backend/.env` — application, database, mail, session, and server configuration.
- `Backend/storage/app/public/products/` — administrator-uploaded product images/video. `MediaService` derives `products/` from the `Product` model.
- `Backend/storage/app/public/` — all current/future public media on the Laravel `public` disk.
- `Backend/storage/app/private/` — private runtime/import data.
- `Backend/storage/icard/` — legacy PEM fallback files.
- `Backend/storage/logs/` — application logs.
- `Backend/storage/framework/cache/` — generated cache.
- `Backend/storage/framework/sessions/` — session files if the file session driver is selected.
- `Backend/storage/framework/views/` — compiled views.
- `Backend/database/backups/` — any manually created database backups.
- `root/storage` — server-created public-media link; it points to persistent `Backend/storage/app/public/`.

Order invoice HTML is generated in the HTTP response by `OrderController` and is not written to disk. No other generated order-file directory was found. No content/user upload controller other than the shared media service was found. These protected paths are removed from the isolated build and excluded again by the FTPS command, so existing testing files are neither overwritten nor deleted.

## Apple Pay and deployment safety

The protected production verification file is:

```text
smisul.bg/root/.well-known/apple-developer-merchantid-domain-association
```

The complete `.well-known/` tree is removed from the staging build and excluded by the upload command. It is not copied to testing. Because only testing secret paths are accepted and no clean/delete synchronization is used, the production file and production directories remain untouched.

The workflow performs PHP linting, installs locked Composer production dependencies, builds the existing frontend, prepares isolated trees, verifies protected paths are absent, and uploads via explicit FTPS. It does not execute the installer, migrations, seeders, SQL imports, payment callbacks, refunds, reversals, or any destructive endpoint. Its homepage check is non-destructive and warns rather than failing while DNS/subdomain setup is incomplete.

## Safe rollback

Select a previously working commit, merge or cherry-pick it into `Deployment`, review it, and push `Deployment` again. The same upload/update-only process will restore tracked application files. Server-only `.env`, uploaded media, iCard runtime files, logs, sessions, and other remote-only files remain in place. Database rollback is a separate manual operation and must only ever target the testing database.

Production deployment is intentionally not configured by this task.
