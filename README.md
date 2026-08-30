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

This also seeds the full development product catalog — see
[Development seed data](#development-seed-data) below for what it
contains and for the one-time `storage:link` step media needs.

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

## API overview

Every route below is versioned under `/api/v1` so a future breaking change
can ship as `/api/v2` without disrupting existing clients. "Auth" columns:
blank = public/no auth, `✓` = requires an authenticated session
(`auth:sanctum`), `mixed` = works for both a guest (via a token) and an
authenticated owner, `admin` = `auth:sanctum` + the `administrator` role.

## Authentication

| Method | Path                                    | Auth | Purpose                          |
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
- **`UserResource` carries `outstanding_legal_documents`** on every auth
  response (login/register/`/profile`) — the Terms/Privacy versions the
  user hasn't accepted yet (never accepted, declined, or accepted an older
  version). The frontend uses this to trigger a re-acceptance prompt; see
  Legal documents below.

## Product catalog

Public routes are read-only and only ever return published products /
active categories; admin routes require `auth:sanctum` + the
`administrator` role (`admin` middleware alias).

| Method | Path                                       | Auth  | Purpose                        |
|--------|---------------------------------------------|:---:|-----------------------------------|
| GET    | `/api/v1/products`                         |     | List products (search/filter/sort/paginate) |
| GET    | `/api/v1/products/{slug}`                  |     | Show a product with variants, prices, media |
| GET    | `/api/v1/products/{slug}/variants`         |     | List a product's variants        |
| GET    | `/api/v1/products/{slug}/reviews`          |     | Paginated approved reviews for a product (see Reviews below) |
| GET    | `/api/v1/products/{slug}/reviews/summary`  |     | Rating average/count/distribution |
| GET    | `/api/v1/categories`                       |     | Category tree (active only)      |
| GET    | `/api/v1/categories/{slug}`                |     | Show a category                  |
| GET    | `/api/v1/categories/{slug}/products`       |     | Products within a category       |
| *      | `/api/v1/admin/products`, `/admin/categories`, `/admin/promotions` | admin | Full CRUD (see Admin API below) |
| POST/PUT/DELETE | `/api/v1/admin/products/{product}/variants[/{variant}]` | admin | Variant CRUD |
| PUT    | `/api/v1/admin/products/{product}/variants/{variant}/price` | admin | Set price (logs `PriceHistory`) |
| POST/DELETE | `/api/v1/admin/products/{product}/media[/{media}]` | admin | Attach/remove media |

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
  duplicating columns. The admin media library (`/admin/media`, see Admin
  API below) is a cross-cutting, unscoped view over the same `Media` table.

## Cart

Open to both guests and authenticated customers — no `auth:sanctum`
middleware, since that would 401 guests. A guest cart is identified by an
`X-Guest-Cart-Token` header (a UUID the frontend generates and persists,
must validate as a UUID or the request 422s); an authenticated cart is
identified by the session. See `CartController::resolve()` /
`CartService::resolveCart()`.

| Method | Path | Auth | Purpose |
|--------|------|:---:|---------|
| GET    | `/api/v1/cart`               |     | Show the current cart |
| POST   | `/api/v1/cart/items`         |     | Add an item (merges quantity if the variant is already in the cart) |
| PATCH  | `/api/v1/cart/items/{item}`  |     | Update a line's quantity |
| DELETE | `/api/v1/cart/items/{item}`  |     | Remove a line |
| DELETE | `/api/v1/cart`               |     | Clear the cart |

Notes on the design:

- **Inventory is reserved at add-to-cart time, not at checkout** —
  `CartService::addItem()` locks the variant's inventory row and calls
  `InventoryService::reserve()` for exactly the newly-added quantity
  (validated against what's genuinely free across every cart right now,
  not the running cart total, so a cart's own prior reservation for that
  line is never double-counted against itself).
- **Guest → user merge on login**: `CartService::resolveCart()` merges a
  guest cart into the user's cart (`mergeGuestCartIfExists`) the first time
  a request carries both a session and a guest token — the guest cart is
  then left empty, not deleted.
- **Per-line quantity cap**: `CartPricingService::MAX_QUANTITY_PER_ITEM` —
  exceeding it throws `InsufficientStockException`, the same exception
  used for genuine out-of-stock.

## Checkout

Same guest/authenticated resolution as cart — placing an order never
requires an account.

| Method | Path | Auth | Purpose |
|--------|------|:---:|---------|
| GET  | `/api/v1/checkout/shipping-methods` |   | The flat-rate catalog: every (carrier, delivery type) combination currently offered, no network call |
| GET  | `/api/v1/checkout/shipping-quote`   |   | A live, destination-aware price/ETA for one carrier + delivery type (falls back to the flat rate if the carrier API is unreachable) |
| GET  | `/api/v1/checkout/shipping-offices` |   | Offices/lockers for a carrier, optionally filtered by city |
| GET  | `/api/v1/checkout/settlements`      |   | The full Bulgarian settlement list (towns/cities/villages) for the "Населено място" picker shown for home delivery |
| GET  | `/api/v1/checkout/legal-documents`  |   | Only the subset of current legal documents required at checkout (4 of 6 types — see Legal documents below) |
| GET  | `/api/v1/checkout/payment-methods`  |   | Every offerable payment method, each flagged `available` for the given `?carrier=` (see Payments below) |
| POST | `/api/v1/checkout/orders` (throttled: `checkout`) | | Place the order and auto-initiate payment in the same request |

Notes on the design:

- **`shipping-methods` vs `shipping-quote`**: the former is the always-cheap
  catalog list used to populate the checkout method list (each provider's
  own hardcoded/admin-configured flat rate — see Shipping below); the
  latter makes a real carrier API call for a destination-aware price and is
  not used to determine what the customer is actually charged.
- **Placing an order and starting payment happen in one DB transaction**
  (`CheckoutController::placeOrder()`): `OrderService::placeOrder()` then
  `PaymentService::initiate()`, so an order is never left without at least
  a `Pending`-status payment attempt.
- **Card is the default** `payment_method` when the field is omitted.
- The response includes `meta.guest_access_token` (used for guest
  order/shipment/payment lookup afterward) and the just-initiated
  `payment` object alongside the order.

## Shipping

Two independent carrier integrations behind one interface
(`ShippingProviderInterface` → `ShippingService`) — **Speedy** (staffed
office, automated machine, or home delivery) and **BOX NOW** (locker
only). Econt was integrated earlier
and later removed entirely — its `ShippingCarrier::Econt` enum case is kept
only so historical orders/shipments already placed with it still cast and
display correctly; `ShippingCarrier::active()` (not `::cases()`) is what
every checkout/validation/admin code path uses to mean "carriers a
customer can pick today", so it can never resurface as a live option.

- **Pricing**: each provider has a hardcoded flat rate per delivery type,
  overridable per delivery type by an administrator
  (`shipping_provider_settings.price_office` / `price_locker` /
  `price_address` — see `ShippingProviderSettingsService::priceFor()`). An
  admin-set price is the single source of truth for both the checkout
  catalog price and the order's actual charged `shipping_price` — there's
  no separate "display price" vs "billed price".
- **Credentials**: same DB-override-with-env-fallback shape as pricing
  (`ShippingProviderSettingsService::credentialsFor()`), configured at
  **Settings → Shipping** (`/api/v1/admin/shipping-settings`, see Admin API
  below).
- **BOX NOW** is a real production integration (`BoxNowShippingProvider`)
  against their Partner API: OAuth2 client-credentials auth,
  `GET destinations` for lockers (fetched unfiltered, filtered client-side
  by the frontend's city/office pickers), `POST delivery-requests` for
  shipment creation, `GET parcels` for tracking. `paymentMode` is
  `"prepaid"` for every order now — cash on delivery was removed (see
  Payments below); the `"cod"` branch (with the real `amountToBeCollected`)
  only still fires for a historical order whose payment was placed before
  the removal.
- **Speedy** (`SpeedyShippingProvider`) is a real Web API integration
  verified against their sandbox — `calculate` (quote), `location/office`
  (a single lookup returns both staffed offices and APT/automated-machine
  entries, distinguished by the office's own `type` field and mapped to
  `ShippingDeliveryType::Office`/`Locker` respectively — both are real
  checkout options, shown as separate "Speedy" / "Speedy (автомат)" rows;
  see `ShippingService::label()`), `shipment` (creation, requires splitting
  a free-text address line into street name + number), `track`.
- Every shipment creation/tracking call is admin-triggered, not automatic —
  placing an order does not create a carrier shipment by itself.
- Shipment tracking is read via `GET /api/v1/orders/{order}/shipment` (see
  Orders below) — it serves persisted state, it does not poll the carrier
  live on every request.

## Payments

Card (iCard hosted modal) is the only payment method — cash on delivery
(previously offered for BOX NOW orders only) was removed entirely.
`PaymentMethod::CashOnDelivery` stays in the enum purely so historical
`payments` rows placed while it was still offered still cast and display
correctly (`PaymentMethod::active()`, not `::cases()`, is what every
checkout/validation path uses to mean "methods a customer can pick
today" — same pattern as `ShippingCarrier::active()` for Econt).

| Method | Path | Auth | Purpose |
|--------|------|:---:|---------|
| POST | `/api/v1/payments/{order}/initiate` (throttled: `checkout`) | mixed | (Re-)start a payment attempt for an order, optionally with a different method than the original |
| GET  | `/api/v1/payments/{order}/status` | mixed  | Poll the authoritative current payment status |
| POST | `/api/v1/payments/{order}/return`  | mixed  | Logged when the customer's browser lands back on the success/failed page; opportunistically reconciles with the gateway |
| POST | `/api/v1/payments/{order}/cancel`  | mixed  | Customer-initiated cancel before completing payment |
| POST | `/api/v1/payments/webhook/icard` (throttled: `webhooks`) | | iCard's async server-to-server notification — trust comes from RSA signature verification, not a session |
| GET  | `/api/v1/stored-payment-methods` | ✓ | List a customer's saved (tokenized) cards |
| DELETE | `/api/v1/stored-payment-methods/{storedPaymentMethod}` | ✓ | Remove a saved card |

Notes on the design:

- **Ownership, not just auth**: `/payments/*` routes carry no
  `auth:sanctum` middleware — a guest who placed an order needs to reach
  them too. Access is checked per-request against either the authenticated
  user's ownership or a `?token=` matching the order's
  `guest_access_token`, compared with `hash_equals()`
  (`PaymentController::authorizeAccess()`, mirrors `OrderController`).
- **Only one real gateway integration**: `ICardPaymentGateway` (card) — the
  only method `PaymentMethod::active()` returns, so `PaymentService::initiate()`
  always goes through it now.
- **Every retry mints a fresh attempt** (new `transaction_reference`)
  rather than reusing a prior non-final `Payment` row — iCard itself
  rejects a resubmitted MID+OrderID, so an order-level idempotency
  shortcut here would be actively wrong.
- **Apple Pay / Google Pay are not separate checkout methods.** They render
  as trust-mark badges under the card option and, if enabled for the
  merchant MID, are offered *inside* iCard's own hosted modal — there is no
  application-owned wallet SDK integration or dedicated wallet endpoints
  (an earlier attempt at a separate wallet flow was built and later
  reverted; see `docs/icard-integration.md`).
- `PaymentService::availablePaymentMethods(?ShippingCarrier)` is the single
  source of truth for what's actually *selectable* (drives
  `PlaceOrderRequest` validation and the retry endpoint's validation);
  `PaymentService::offerableMethods()` is what's always *listed* — see
  `checkout/payment-methods` above.
- **Stored cards**: tokenized references only (`card_token`/`token_hash`
  encrypted and hidden from every response) — removing one is a soft
  delete (`is_active = false`), not a row deletion.
- **Admin operations** (reverse/refund) are under
  `/admin/payments/{payment}/*` — see Admin API below.

## Orders

| Method | Path | Auth | Purpose |
|--------|------|:---:|---------|
| GET | `/api/v1/orders` | ✓ | Authenticated customer's own order history, paginated, newest first |
| GET | `/api/v1/orders/{order}` | mixed | Single order detail |
| GET | `/api/v1/orders/{order}/invoice` | mixed | Invoice document |
| GET | `/api/v1/orders/{order}/shipment` | mixed | Shipment tracking detail (persisted state, not a live carrier poll) |

Notes on the design:

- **Guest order access**: guest orders have `user_id = null`;
  `guest_access_token` is minted at placement
  (`CheckoutController::placeOrder`) and must be passed back as `?token=`,
  checked with `hash_equals()` (timing-safe comparison) against the stored
  token.
- **Registered-customer access**: `OrderPolicy::view` requires
  `order->user_id !== null && authUser->is(order->user)` — a guest order
  can never be claimed by a logged-in user's session, only by the token.
  The identical ownership check is duplicated in both `OrderController`
  and `ShipmentController`, not shared via a policy/trait.
- **Invoice is HTML, not a real PDF invoice**: rendered from a Blade view
  and returned as `text/html` with `Content-Disposition: attachment`
  (downloads as `invoice-{order_number}.html`) — explicitly a placeholder,
  not a legally-numbered tax invoice with VAT breakdown.
- **Shipment tracking** returns 404 if the order has no shipment row yet
  (not every order has one before dispatch); the response includes the
  full status-event history whenever one exists.

## Reviews

| Method | Path | Auth | Purpose |
|--------|------|:---:|---------|
| GET    | `/api/v1/customer/reviews` | ✓ customer | List authenticated user's own reviews |
| POST   | `/api/v1/customer/reviews` | ✓ customer | Create a review (requires `product_variant_id` + `order_id`) |
| PUT    | `/api/v1/customer/reviews/{review}` | ✓ owner | Update own review (rating/title/body) |
| DELETE | `/api/v1/customer/reviews/{review}` | ✓ owner | Delete own review |
| POST   | `/api/v1/reviews/{review}/helpful` | ✓ any | Toggle a "helpful" vote |

(Public read endpoints — `GET /products/{slug}/reviews` and
`/reviews/summary` — are listed under Product catalog above.)

Notes on the design:

- **Eligibility** (`ReviewService::assertEligible`, checked in fixed order,
  each failure a distinct 422 `ReviewNotEligibleException`, not a policy
  403): (1) the order belongs to the reviewing user, (2) the order
  actually contains the variant, (3) the order status is `Delivered`,
  (4) no existing review for that order+product pair already.
- **No pre-publication moderation queue**: reviews are created
  `status = Approved` and `verified_purchase = true` immediately.
  Moderation (approve/reject/hide) is post-hoc, admin-only — see Admin API
  below.
- Editing/deleting your own review is unrestricted by status or time
  window — ownership is the only check. Editing does not reset an
  admin-hidden/rejected review back to approved.
- **`markHelpful` is a toggle**, not a one-shot vote — voting again
  withdraws it. Backed by a unique-per-user `ReviewVote` row plus a
  denormalized `helpful_count`, kept in sync inside a locked transaction.
  Only allowed on `Approved` reviews, and a review's own author can't vote
  it helpful; unlike the CRUD routes, this isn't customer-only — any
  authenticated user (including admins) can vote.

## Favorites

Authenticated customers only — an admin gets 403 from `FavoritePolicy`
(same as a guest's 401 from `auth:sanctum`, one layer up).

| Method | Path | Auth | Purpose |
|--------|------|:---:|---------|
| GET    | `/api/v1/customer/favorites` | ✓ customer | List favorites |
| POST   | `/api/v1/customer/favorites` | ✓ customer | Add a product variant to favorites |
| GET    | `/api/v1/customer/favorites/count` | ✓ customer | Count of favorited variants |
| GET    | `/api/v1/customer/favorites/check/{productVariant}` | ✓ customer | Check favorited state for one variant |
| DELETE | `/api/v1/customer/favorites/{favorite}` | ✓ owner | Remove a favorite |

Notes on the design:

- Duplicate prevention: `FavoriteService::add()` throws
  `DuplicateFavoriteException` if the (user, variant) pair already exists.
- The `check` endpoint is a lightweight per-variant alternative to loading
  the full list — the frontend actually uses the full-list approach.

## Legal documents & consent

| Method | Path | Auth | Purpose |
|--------|------|:---:|---------|
| GET  | `/api/v1/legal-documents` |   | Every current document, all 6 types |
| GET  | `/api/v1/legal-documents/{slug}` |   | Fetch one current document by slug |
| GET  | `/api/v1/consent/cookies` |   | Current cookie preference state |
| POST | `/api/v1/consent/cookies` |   | Store cookie preferences |
| POST | `/api/v1/consent/legal-documents/accept` | ✓ | Re-accept all outstanding Terms/Privacy after a published update |

Notes on the design:

- **Document types**: `TermsOfService`, `PrivacyPolicy`,
  `RightOfWithdrawal`, `CookiePolicy`, `ShippingPolicy`, `ReturnsPolicy`.
  GDPR disclosures are folded into `PrivacyPolicy` — there's no separate
  GDPR document type.
- **Versioning is append-only**: publishing a new version
  (`LegalDocumentService::publish`) inserts a *new* row and flips
  `is_current`, never mutating an already-accepted row — so a past order's
  `OrderLegalAcceptance` (or a `Consent` row) always points at the exact
  version the customer actually saw.
- **Checkout requires a subset**: `GET /api/v1/checkout/legal-documents`
  returns only `LegalDocumentType::requiredAtCheckout()` — Terms, Privacy,
  RightOfWithdrawal, CookiePolicy. ShippingPolicy/ReturnsPolicy are
  informational-only public pages, never required as a checkbox.
  `requiredForAccount()` is narrower still — just Terms + Privacy — and is
  what drives `UserResource::outstanding_legal_documents` (see
  Authentication above) and the re-acceptance endpoint.
- **Consent is a separate, append-only audit table** — distinct from
  `OrderLegalAcceptance`, which continues to handle order-time acceptance.
  Every write is an insert, never an update; "current state" is just the
  most recent row. Cookie categories: `necessary` (always `true`, not a
  real toggle), `analytics`, `marketing`, `preferences`. Guest vs.
  authenticated is `user_id` XOR a client-supplied `guest_identifier`,
  never both.
- **Known limitation**: `GET /api/v1/consent/cookies` can't distinguish
  "never decided" from "explicitly rejected everything" — the cookie
  banner's visibility is therefore driven by a local `localStorage` flag,
  not this endpoint. The backend call is the audit trail; it's
  fire-and-forget and never blocks the UI.

## Contact & funnel

| Method | Path | Auth | Purpose |
|--------|------|:---:|---------|
| POST | `/api/v1/contact` (throttled: `contact`) |   | Submit the contact form |
| GET  | `/api/v1/funnel` |   | Funnel-mode config + landing-page content for storefront boot |
| POST | `/api/v1/funnel/leads` (throttled: `funnel-leads`) |   | Capture the landing page's email opt-in |
| GET  | `/api/v1/content/homepage` |   | Editable homepage content sections |
| GET  | `/api/v1/settings/public` |   | Whitelisted merchant identity/contact fields for the footer |

Notes on the design:

- **Contact form has no DB persistence** — the message is emailed directly
  (`Mail::to(config('mail.contact_address'))`); there's no admin "contact
  messages" list.
- **"Funnel mode"** is a single admin-controlled boolean
  (`FunnelConfig::current()->is_enabled`) that swaps the storefront's
  normal homepage for a single-product landing page and hides
  search/Favorites. An unpublished/deleted featured product or a stale
  package→variant reference silently drops out of the public payload
  rather than erroring it.
- **Funnel lead capture never confirms whether an email is already on the
  list** — `firstOrCreate` returns the same 201 either way, deliberately,
  so the endpoint can't be used to probe the list. A welcome email (with a
  usage-manual PDF) sends only on first capture; a mail failure is logged,
  never loses the captured lead. `FunnelLead` is deliberately minimal — no
  user linkage, no double opt-in.
- **Homepage content** is 8 fixed sections (`hero, featured, benefits,
  usage, trust, delivery, bio, faq`), one `ContentBlock` row each, always
  present in the response even before any admin edit (defaults to `[]`).
  `featured` resolves an admin-picked `product_id` to the `product_slug`
  the storefront actually needs, falling back to `null` if that product
  was since unpublished.
- **`settings/public` is a strict whitelist**, not a flag-on-row mechanism
  — the public keys (`company_name`, `company_id`, `contact_address`,
  `support_phone`, `store_email`, `same_day_dispatch_cutoff`, the
  `social_*` links, etc.) are hardcoded in `SettingService::publicSettings()`
  so a future admin-added setting can never leak in by accident.

## Admin API

Everything under `/api/v1/admin/*` is gated by `auth:sanctum` + the
`admin` middleware (administrator role). Every write across every admin
controller below is logged via `AdminActionLogger` unless noted otherwise.

### Products, categories, promotions

| Method | Path | Purpose |
|--------|------|---------|
| GET/POST | `/api/v1/admin/products` | List (all statuses) / create |
| GET/PUT/PATCH/DELETE | `/api/v1/admin/products/{product}` | Show / update / delete |
| POST | `/api/v1/admin/products/{product}/variants` | Create a variant |
| PUT/DELETE | `/api/v1/admin/products/{product}/variants/{variant}` | Update / delete a variant |
| PUT | `/api/v1/admin/products/{product}/variants/{variant}/price` | Set price (per currency) |
| PUT | `/api/v1/admin/products/{product}/variants/{variant}/inventory` | Set on-hand stock |
| POST | `/api/v1/admin/products/{product}/media` | Attach media |
| PATCH | `/api/v1/admin/products/{product}/media/{media}/primary` | Mark media as primary |
| DELETE | `/api/v1/admin/products/{product}/media/{media}` | Detach media |
| * | `/api/v1/admin/categories[/{category}]` | Full CRUD (tree), including inactive categories |
| * | `/api/v1/admin/promotions[/{promotion}]` | Full CRUD |

- `PriceService::setPrice` upserts one `Price` row per variant+currency and
  appends a `PriceHistory` row only when the amount actually changed — the
  update endpoint always returns 200, never 201 (whether the row was
  inserted or updated is an implementation detail).
- Stock and price are deliberately split from the general variant update —
  separate endpoints from sku/name/pack_size etc.
- The admin `ProductResource` adds a convenience `quantity`/`price` pair
  read off the default (or first) variant's EUR price, so single-variant
  products don't need the full variant array in list/form views.
- No resizing/thumbnailing pipeline on media attach — stores the file and
  a DB row only.

### Orders, dashboard, customers

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/admin/orders/statistics` | Order/revenue counters |
| GET | `/api/v1/admin/orders` | List orders (filterable, incl. by `user_id`) |
| GET | `/api/v1/admin/orders/{order}` | Show order (payments, transactions, webhook logs, shipment + status events) |
| PATCH | `/api/v1/admin/orders/{order}/status` | Transition order status |
| GET | `/api/v1/admin/dashboard` | Aggregate KPI snapshot |
| GET | `/api/v1/admin/customers` | List customers (filterable) |
| GET | `/api/v1/admin/customers/{user}` | Show one customer + `orders_count` |

- `updateStatus` targeting `cancelled` routes through `OrderService::cancel()`
  so any held stock reservation is released; every other target goes
  through `OrderStatusService::transitionTo()`, validated against a state
  machine. Inventory is only released if the order was still
  `Pending`/`AwaitingPayment` — a later cancellation needs a real
  restock/refund flow (out of scope).
- The dashboard is deliberately thin: every figure is either a straight
  `OrderService::statistics()` reuse or a single count/`whereRaw` query,
  not a separate reporting engine. Low/out-of-stock counts are computed
  inline from `quantity_on_hand - quantity_reserved` against
  `low_stock_threshold`/`backorders_allowed`, not cached.
- Customers are read-only here — no create/update/delete; accounts are
  managed via normal auth/registration. There's no embedded
  per-customer order history endpoint; the admin frontend reuses
  `GET /admin/orders?user_id={id}` instead.

### Settings, payments, shipping

| Method | Path | Purpose |
|--------|------|---------|
| GET/PUT | `/api/v1/admin/settings` | Editable general settings (grouped) + provider configured-status |
| GET | `/api/v1/admin/payment-settings/icard` | iCard config profiles (sandbox/production) |
| PUT | `/api/v1/admin/payment-settings/icard/{environment}` | Update one environment's iCard config |
| POST | `/api/v1/admin/payment-settings/icard/{environment}/activate` | Make an environment live |
| GET | `/api/v1/admin/shipping-settings` | List every provider's config |
| PUT | `/api/v1/admin/shipping-settings/{provider}` | Update one provider (credentials + per-delivery-type pricing) |
| POST | `/api/v1/admin/payments/{payment}/reverse` | Void an authorized/paid iCard payment before order processing |
| POST | `/api/v1/admin/payments/{payment}/refund` | Refund some or all of a paid iCard payment |

- **Three genuinely separate concerns, not one blob**: `Setting` (DB table,
  groups `general/email/seo/media/system`) holds editable key/value pairs;
  iCard and shipping credentials/pricing live in their own dedicated,
  DB-backed-with-env-fallback config services reached via their own
  routes. The general settings endpoint only surfaces a read-only
  `providers` status block — actual credentials/prices never round-trip
  through it.
- If the relevant migration hasn't run, the iCard/shipping settings
  endpoints short-circuit to a 503 with a `*_MIGRATION_REQUIRED` code
  rather than 500ing on missing columns.
- Secrets are never echoed back and are stripped from the audit-log
  payload before logging — the log records that a credential changed, not
  its value.
- **Reverse vs. refund** is a pre/post-processing distinction: `reverse`
  only works while the order is still `AwaitingPayment`/`Paid` (before
  fulfillment); `refund` requires `status === Paid` and supports partial
  amounts (string-math against the remaining refundable amount, not
  floats). Both require a `gateway_transaction_reference` already
  confirmed by iCard, and both are iCard-only. A reversal cancels the
  order; a full refund moves it to `Refunded` (a partial refund leaves
  order status alone).

### Content, legal, media, logs

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/admin/content/homepage` | All 8 homepage content sections |
| PUT | `/api/v1/admin/content/homepage/{section}` | Update one section |
| GET/POST | `/api/v1/admin/legal-documents` | List published documents / publish a new version |
| GET | `/api/v1/admin/media` | Cross-model media library: search/filter by mediable type, mime type |
| POST/DELETE | `/api/v1/admin/media/{media}` | Replace the file in place / delete |
| GET | `/api/v1/admin/logs?type=...` | Paginated log entries for one of 5 tabs |

- `{section}` for homepage content is route-constrained to
  `ContentBlockService::HOMEPAGE_SECTIONS` — an invalid section 404s at
  routing, before the controller runs.
- Legal documents are append-only here too — there's deliberately no
  `update()`/`destroy()`, matching the versioning model described above.
- The media library is the same `Media` model/table product media uses —
  a cross-cutting, unscoped view (searchable by mediable type/mime type),
  not a separate library. `replace()` keeps the same row id (swaps the
  underlying file), so anything referencing that media by id keeps
  working. There's no `store()` here — new media is only ever created via
  a mediable-specific attach endpoint.
- **Logs cover 5 different immutable source tables**, normalized into one
  shape: `orders` → `OrderStatusHistory`, `payments` → `PaymentTransaction`,
  `shipments` → `ShipmentStatusEvent`, `authentication` →
  `AuthenticationLog`, `admin_actions` → `AdminActionLog`.
  `AdminActionLog` deliberately doesn't duplicate order status changes
  (which already have their own trail) — it covers everything else:
  catalog CRUD, settings changes, legal publishing, funnel/content edits,
  payment reverse/refund, media/lead operations.

### Reviews moderation

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/admin/reviews` | List reviews (filter by status/product/rating/search) |
| GET | `/api/v1/admin/reviews/statistics` | Counts by status + average rating |
| POST | `/api/v1/admin/reviews/bulk-moderate` | Apply one status to many review ids |
| POST | `/api/v1/admin/reviews/{review}/approve` | Approve |
| POST | `/api/v1/admin/reviews/{review}/reject` | Reject (with reason) |
| POST | `/api/v1/admin/reviews/{review}/hide` | Hide |
| POST | `/api/v1/admin/reviews/{review}/reply` | Post an admin reply |
| DELETE | `/api/v1/admin/reviews/{review}` | Permanently delete |

- Moderation states aren't symmetric: approve/reject both notify the
  author; `hide` is a quiet takedown (e.g. a post-approval TOS violation)
  that does **not** notify. `destroy` is the only non-reversible action —
  reject/hide can both be walked back with a later approve.
- Bulk moderate replays the single-item methods row by row (not a bulk SQL
  update), so per-review events/notifications fire exactly as they would
  one at a time. `Pending` as a bulk target is a no-op.
- A reply is additive metadata (`admin_reply`/`admin_reply_at`/
  `admin_replied_by`) — it doesn't change the moderation `status`.

### Funnel admin

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/admin/funnel` | Full funnel admin payload (enabled flag, product/packages, content) |
| PUT | `/api/v1/admin/funnel/toggle` | Enable/disable the funnel landing page |
| PUT | `/api/v1/admin/funnel/packages` | Set the funnel's featured product + package tiers |
| PUT | `/api/v1/admin/funnel/content/{section}` | Update one funnel content section |
| POST | `/api/v1/admin/funnel/faq-attachment` | Upload a PDF for an FAQ item |
| GET | `/api/v1/admin/funnel/leads` | List captured leads, paginated, newest first |
| GET | `/api/v1/admin/funnel/leads/export` | Stream leads as CSV |
| DELETE | `/api/v1/admin/funnel/leads/{lead}` | Delete a lead (GDPR erasure) |

- **Single-row config** — `FunnelConfig::current()` is one record
  (`is_enabled`, `product_id`, `packages` JSON), not a per-campaign table.
- Funnel content sections (`hero, intro, why, features, comparison,
  history, natural_eco, science, awareness, positioning, final_cta, faq`)
  are a separate list from the homepage's, kept in their own service even
  though both persist through the same `content_blocks` table (keyed
  `funnel.{section}` vs `homepage.{section}`).
- The FAQ attachment upload bypasses the media service entirely — it's a
  standalone PDF stored directly on the public disk, returning `{url,
  filename}`; the admin still has to save the FAQ content section
  afterward for the URL to take effect.
- `/funnel/leads/export` is declared before `/{lead}` specifically so it
  isn't swallowed by the model-binding wildcard. Export streams CSV
  500-at-a-time rather than loading everything into memory.

## Development seed data

One command rebuilds the entire development dataset from scratch:

```bash
cd backend
php artisan migrate:fresh --seed
```

Then, once (per machine/deploy), so seeded media URLs actually resolve:

```bash
php artisan storage:link
```

This creates the admin/customer accounts (see Authentication above) plus a
7-product catalog across 3 categories, deliberately built to exercise every
storefront/cart scenario rather than just "happy path" data:

| Product | Category | Variants | Notable scenario |
|---|---|---|---|
| Smisul Original | Original | 1/3/6/12 бр. | Active promotion, one variant on sale, 4 images, PDF, video |
| Био билкова смес | Билки и чайове | 50г/100г | On sale, low stock, expired *and* active (inherited) promotions at once |
| Био микс от семена | Ядки, семена и масла | 200г/500г | No promotion, no images, one out-of-stock variant |
| Студено пресовано масло | Ядки, семена и масла | 250мл/500мл | Backorder-enabled variant (purchasable at 0 on-hand) |
| Натурални енергийни хапки | Ядки, семена и масла | 150г (single) | Single-variant product — variant picker hides itself |
| Уелнес чай | Билки и чайове | 20/40 пакетчета | Promotion inherited from its category only |
| Премиум микс от ядки | Ядки, семена и масла | 150г/300г/500г | Three variants, one low-stock |

Every product has full Bulgarian copy (short/long description with
benefits, usage, storage, ingredients, and FAQ sections), SEO metadata
(title/description/keywords/OG), and image alt text — see
`database/seeders/ProductSeeder.php`.

**No binary media is committed to the repo.** Product photos (SVG), the
demo PDF, and the demo video are all generated at seed time by
`App\Support\PlaceholderMedia` and written to `storage/app/public/...`,
so the dataset is fully reproducible from `migrate:fresh --seed` with zero
binary drift. See `backend/resources/seed-media/README.md` for where real
assets belong once they exist, and that class's docblocks for why the demo
PDF/video are deliberately simple (no PDF/video libraries are installed —
the PDF is still a genuinely valid, openable file; the video is a labeled
text stub that exercises the UI path without being a real playable clip).

Re-running the seeders (with or without `migrate:fresh` first) is safe —
every write is `updateOrCreate`/`sync`, never a blind insert, so editing
`ProductSeeder.php`'s content and re-seeding updates existing rows instead
of duplicating them.

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

See `CHANGELOG.md` for the full history. The full storefront is live: auth,
product catalog, cart, checkout (Speedy + BOX NOW shipping, card-only
payment), orders, shipment tracking, reviews, favorites, a
funnel/landing-page mode, and a full admin panel covering catalog,
orders, customers, settings, payments, shipping, content, legal documents,
media, logs, and funnel management.

**Payments** run on iCard's embedded modal (card) — see
`docs/icard-integration.md` for the IPG protocol details and
`docs/wallet-payments.md` for the history of a since-reverted separate
Apple Pay / Google Pay SDK integration (wallets are currently trust-mark
badges only, rendered inside iCard's own modal).

**Not yet implemented / known gaps**:
- Real PDF tax invoices with legal numbering + VAT breakdown (the invoice
  endpoint currently returns an HTML placeholder).
- Automatic/scheduled shipment creation and live tracking sync — both are
  currently admin-triggered, not automatic.
- A lawyer has not yet reviewed the seeded legal document copy — see
  `docs/legal-gdpr-seo.md`.
