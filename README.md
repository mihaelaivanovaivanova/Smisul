# Smisul

Production e-commerce platform.

## Stack

| Layer    | Technology                                    |
|----------|-------------------------------------------------|
| Backend  | Laravel 12, PHP 8.4, REST API                    |
| Auth     | Laravel Sanctum (SPA cookie/session auth)        |
| Frontend | React, TypeScript, React Router, Axios, Bootstrap 5 |
| Database | MySQL 9.x                                        |
| Backend QA | PHPUnit, Laravel Pint, Larastan (PHPStan)       |
| Frontend QA | TypeScript, oxlint                             |
| Hosting  | SuperHosting (shared hosting)                    |

## Repository layout

```
smisul/
├── backend/        Laravel 12 API application
├── frontend/       React + TypeScript + Vite application
├── docs/           Architecture and design documentation
├── prompts/        AI-assisted development prompts/records
├── scripts/        Automation and maintenance scripts
├── storage/        Project-level shared storage (not app runtime storage)
├── checklists/     Release/QA checklists
├── templates/      Reusable document/code templates
├── research/       Market and technical research notes
├── .gitignore
├── README.md
└── LICENSE
```

## Prerequisites

- PHP 8.4+
- Composer 2.x
- Node.js 20+ and npm
- MySQL 9.x (or compatible 8.x) server running locally
- Git

## Backend setup (`/backend`)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Edit `backend/.env` and set your local MySQL credentials:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smisul
DB_USERNAME=root
DB_PASSWORD=your_password
```

Create the database (if it doesn't already exist):

```sql
CREATE DATABASE smisul CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Set a seeded administrator's credentials (required — the seeder refuses to
run without these; there is no hardcoded fallback):

```
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=choose-a-strong-password
```

Run migrations and seed the database:

```bash
php artisan migrate --seed
```

This creates the administrator account from `ADMIN_EMAIL`/`ADMIN_PASSWORD`
above, plus one demo customer (`customer@example.com`, password `password`,
from `UserFactory`'s default). Administrator accounts can **only** be
created this way — public registration always creates a `customer`.

Start the backend dev server:

```bash
php artisan serve
```

The API will be available at `http://localhost:8000`.

## Frontend setup (`/frontend`)

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

The app will be available at `http://localhost:5173`. The API client talks
directly to `VITE_API_URL` (default `http://localhost:8000/api`) over CORS
with credentials — there's no dev-server proxy, since Sanctum's CSRF cookie
must come from the real backend origin.

## Authentication

Backend routes are versioned under `/api/v1`. All endpoints:

| Method | Path                                    | Auth required | Purpose                          |
|--------|------------------------------------------|:---:|-----------------------------------|
| POST   | `/api/v1/auth/register`                  |     | Create a customer account         |
| POST   | `/api/v1/auth/login`                     |     | Start a session (supports `remember`) |
| POST   | `/api/v1/auth/logout`                    | ✓   | End the session                   |
| GET    | `/api/v1/auth/user`                      | ✓   | Fetch the current user            |
| POST   | `/api/v1/auth/forgot-password`           |     | Send a password reset link        |
| POST   | `/api/v1/auth/reset-password`            |     | Reset password with a token       |
| GET    | `/api/v1/auth/email/verify/{id}/{hash}`  |     | Verify email (signed link)        |
| POST   | `/api/v1/auth/email/verification-notification` | ✓ | Resend the verification email |
| GET    | `/api/v1/profile`                        | ✓   | View own profile                  |
| PUT    | `/api/v1/profile`                        | ✓   | Update own profile                |
| PUT    | `/api/v1/profile/password`               | ✓   | Change own password               |

Notes on the design:

- **Roles**: `customer` (default) and `administrator`. The `role` column is
  never mass-assignable — it can't be set via registration or profile
  update, only by `AdminSeeder`.
- **Session auth, not tokens**: the SPA uses Sanctum's cookie-based
  authentication (`EnsureFrontendRequestsAreStateful`), not Bearer tokens.
  Every state-changing request needs a CSRF cookie first — call
  `GET /sanctum/csrf-cookie` before register/login/forgot-password/
  reset-password (axios handles this automatically via `src/api/client.ts`).
- **Password reset & email verification links point at the frontend**
  (`FRONTEND_URL`), not the backend, so the user stays inside the SPA. See
  `AppServiceProvider::configureAuthNotificationUrls()`.
- **Rate limiting**: `login`, `register`, `password-reset`, and
  `email-verification` each have dedicated named limiters (see
  `AppServiceProvider::configureRateLimiting()`).

## Product catalog

Also versioned under `/api/v1`. Public routes are read-only and only ever
return published products / active categories; admin routes require
`auth:sanctum` + the `administrator` role (`admin` middleware alias).

| Method | Path                                       | Auth  | Purpose                        |
|--------|---------------------------------------------|:---:|-----------------------------------|
| GET    | `/api/v1/products`                         |     | List products (search/filter/sort/paginate) |
| GET    | `/api/v1/products/{slug}`                  |     | Show a product with variants, prices, media |
| GET    | `/api/v1/products/{slug}/variants`         |     | List a product's variants        |
| GET    | `/api/v1/categories`                       |     | Category tree (active only)      |
| GET    | `/api/v1/categories/{slug}`                |     | Show a category                  |
| GET    | `/api/v1/categories/{slug}/products`       |     | Products within a category       |
| *      | `/api/v1/admin/products`, `/admin/categories`, `/admin/promotions` | ✓ admin | Full CRUD |
| POST/PUT/DELETE | `/api/v1/admin/products/{product}/variants[/{variant}]` | ✓ admin | Variant CRUD |
| PUT    | `/api/v1/admin/products/{product}/variants/{variant}/price` | ✓ admin | Set price (logs `PriceHistory`) |
| POST/DELETE | `/api/v1/admin/products/{product}/media[/{media}]` | ✓ admin | Attach/remove media |

Notes on the design:

- **Product vs. ProductVariant**: `Product` is the sellable concept;
  `ProductVariant` is the purchasable SKU (pack size — 1/3/6/12 today).
  Repositories exist only for the two aggregate roots (`Product`,
  `Category`) that need real search/filter/pagination — variants, prices,
  inventory, and media are managed through their aggregate's service.
- **Price vs. PriceHistory**: `Price` holds the current amount per variant
  per currency; every change is appended to `PriceHistory` by
  `PriceService`, never edited in place. Money is `DECIMAL(10,2)`, not float.
- **SEO and Media are polymorphic** (`seoable`/`mediable`) via `HasSeo`/
  `HasMedia` traits, shared by `Product` and `Category` rather than
  duplicating columns.
- **`InventoryService` and the entire frontend product/category layer**
  (`src/types/product.ts`, `src/api/{products,categories}.ts`,
  `src/services/productCatalog.ts`, `src/hooks/use{Product,Products,
  Category,Categories,ProductVariants,CategoryProducts}.ts`) are built and
  tested but not yet wired into any route, controller, or page — they're
  the foundation the next sprints (storefront pages, cart/orders stock
  reservation) build on, not dead code.

## Running tests and checks

```bash
# Backend — full test suite (uses an in-memory SQLite DB, not your MySQL one)
cd backend
php artisan test

# Backend — code style
vendor/bin/pint --test

# Backend — static analysis (Larastan/PHPStan, level 5)
vendor/bin/phpstan analyse

# Frontend — TypeScript build + Vite bundle
cd frontend
npm run build

# Frontend — lint (oxlint, not ESLint — chosen at bootstrap for speed)
npm run lint
```

## Production checklist

Settings that differ from the local `.env` shown above:

```
APP_ENV=production
APP_DEBUG=false

# Same registrable parent domain, e.g. smisul.bg (SPA) + api.smisul.bg (API)
SESSION_DOMAIN=.smisul.bg
SANCTUM_STATEFUL_DOMAINS=smisul.bg,www.smisul.bg
FRONTEND_URL=https://smisul.bg
FRONTEND_URLS=https://smisul.bg,https://www.smisul.bg
SESSION_SECURE_COOKIE=true

# A real transactional mailer — "log" only writes to storage/logs/laravel.log
MAIL_MAILER=smtp
```

`ADMIN_EMAIL`/`ADMIN_PASSWORD` must be set per environment — never reuse the
local dev admin password in production.

## Building for production

```bash
# Backend — install without dev dependencies, cache config
cd backend
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache

# Frontend — produce static build in frontend/dist
cd frontend
npm run build
```

## Project status

See `CHANGELOG.md` for the full history. In short:

**Sprint 1 (Authentication & User Management)** — full auth flow
(register, login/logout with remember-me, forgot/reset password, email
verification), two roles (`customer`, `administrator`, seed-only), matching
React pages and route guards.

**Sprint 2 (Product Domain Foundation)** — the full catalog data model
(`Product`, `ProductVariant`, `Category`, `Inventory`, `Price`/
`PriceHistory`, `Promotion`, `Media`, SEO) with Repositories, Services,
DTOs, Policies, and a layered public + admin API. Frontend TypeScript
models/API client/hooks exist but have no UI yet — that's the next sprint.

149 backend tests (Unit + Feature), Pint, and PHPStan (level 5) all pass;
frontend build and lint are clean.

**Not yet implemented** (by design, out of scope until later sprints):
cart, checkout, orders, favorites, reviews, storefront/admin UI.
