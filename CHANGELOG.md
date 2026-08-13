# Changelog

All notable changes to this project are documented in this file, grouped by
development sprint. Format loosely follows [Keep a Changelog](https://keepachangelog.com/).

## Funnel Homepage Section Architecture & Content (2026-08-11)

Rebuilt the Miswak funnel landing page (`FunnelLandingPage.tsx`) as
independently maintainable per-section components, then filled in real
copy/design for the first several sections of the 20-section homepage
spec (`ai/context/06_Website_Specification.md`).

### Added

- **Section architecture**: one component per homepage section under
  `frontend/src/components/funnel/sections/` (Hero, UseCases,
  WhatIsMiswak, CoreBenefits, HowToUse, Science, SkepticismHonesty,
  PositioningStatement (stub), Comparison, ActualProduct,
  FunnelTestimonials, NaturalEco (stub), History, BrandStatement, Pricing,
  DeliveryPaymentReturns, Faq, Newsletter), plus shared `FunnelIcons.tsx`,
  `StickyMobileBuyBar.tsx`, `StickyDesktopBuyBar.tsx`.
  `FunnelLandingPage.tsx` is now a thin composition layer over these.
- **Header**: simplified to logo + a discreet account/login icon + cart
  at every breakpoint — removed the section-anchor nav and header order
  CTA so nothing competes with the Hero's one primary CTA. Account/login/
  orders links added to the footer to keep them reachable.
- **Hero**: new copy (eyebrow/H1/subheadline/CTA), a live-computed
  "packages from X €" + delivery-promise fine-print line (never
  hardcoded), and a 4-item emoji benefit bar. Global
  `scroll-behavior: smooth` (reduced-motion aware) added so in-page CTA
  jumps actually animate.
- **Use Cases** (new section): 4 occasion cards ("after coffee", "after
  lunch", "on the move", "traveling") ahead of the product explainer.
- **What Is Miswak**: rewritten as a plain product explainer (previously
  carried the tradition/history angle, now solely in History); visual is
  a 3-step photo sequence (whole stick → prepared tip → fibers close-up)
  using real product photography for the first two steps, with a clearly
  labeled placeholder for the third (no matching photo exists yet).
- **Core Benefits**: reduced to exactly 3 cards (always at hand /
  naturally simple / no paste) plus a closing statement — added a
  `closing` field to the section's content schema (frontend type +
  backend admin-validation) so it stays admin-editable.
- **How To Use** (new section): 5-step process (peel → soak → chew →
  clean → refresh the tip), an optional product demo video with
  click-to-play/poster-image/lazy-mount handling (no autoplay, zero video
  bytes requested until an explicit click, cropped a few pixels off each
  edge to hide an AI-generation watermark baked into the source clip),
  and a CTA that downloads the FAQ's existing usage PDF directly (falls
  back to scrolling to that FAQ answer if no PDF is configured).
- Seeded a real product demo video (`miswak-how-to-use.mp4`, ~46MB) via
  the same admin-manageable Product media system already used for the
  product photos.

### Changed

- Renamed several section anchor ids for accuracy now that content
  moved: `#benefits`→`#what-is-miswak`, `#why`→`#core-benefits`,
  `#video`→`#how-to-use`, `#compare`→`#comparison`, `#buy`→`#pricing`.
  Verified no CSS selects by these ids, so the renames are visually
  inert.
- Consolidated the old duplicated "early" + "final" checkout blocks into
  a single Pricing section, per the new section order.

### Removed

- Old Core Benefits card 2 ("Miswak doesn't replace your regular
  toothbrush") and card 3 (yearly-discarded-toothbrushes eco stat) —
  replaced, not relocated. The complement-not-replacement message still
  lives in the FAQ's "Замества ли четката и пастата?" answer but is no
  longer always-visible; the eco stat has no duplicate elsewhere and is
  gone.
- Dead CSS/copy this work orphaned: `.funnel-hero__rating*`,
  `.funnel-mobile-clamp`, `.funnel-assurance__item`, the `funnelNav`
  copy export.

### Known follow-ups

- The seeded demo video is ~46MB and uncompressed — no ffmpeg was
  available in this environment to shrink it before committing.
- What Is Miswak's third visual step ("fibers close-up") has no real
  product photo yet.
- Root `README.md`'s "Project status"/"Development seed data" sections
  describe the project as of Sprint 2 (no cart/checkout/reviews/admin,
  "no binary media committed") — both are long out of date relative to
  the current codebase and predate this work; left alone here since
  fixing them accurately needs the history of every sprint since, not
  just this one.
- Two pre-existing test failures are unrelated to this work
  (`SettingsAdminTest`, `FunnelTest`'s review-seeding assertion).

## Sprint 2 — Product Domain Foundation (2026-07-03)

Architecture-focused sprint: the complete backend data model and layered
API for the product catalog, built to support thousands of products even
though the MVP ships with one. No storefront or admin UI.

### Added

- **Domain model**: `Category` (self-referencing hierarchy), `Product`
  (sellable concept), `ProductVariant` (purchasable SKU — pack size
  1/3/6/12), `Inventory` (1:1 stock per variant), `Price` (current amount
  per variant/currency) + `PriceHistory` (append-only audit log),
  `Promotion` (percentage/fixed, scoped to products/categories via
  pivots), `Media` and `Seo` (polymorphic, shared via `HasMedia`/`HasSeo`
  traits), plus a `Sluggable` trait (unique slug generation with
  collision handling) shared by `Product` and `Category`.
- **Layered architecture**: `ProductRepositoryInterface` /
  `CategoryRepositoryInterface` with Eloquent implementations, bound via
  `RepositoryServiceProvider` — repositories exist only for the two
  aggregate roots with real query complexity (search/filter/sort/
  paginate); child entities (variants, prices, inventory, media) are
  managed through their aggregate's service.
- 7 Services (`ProductService`, `ProductVariantService`, `CategoryService`,
  `PriceService`, `InventoryService`, `MediaService`, `PromotionService`),
  6 DTOs, 3 custom domain exceptions (`ProductNotFoundException`,
  `CategoryNotFoundException`, `DuplicateSkuException`,
  `InsufficientStockException`), 3 Policies, an `admin` role middleware,
  Form Requests, API Resources, and controllers.
- Public read API (`/api/v1/products`, `/api/v1/categories`, ...) and
  admin CRUD API (`/api/v1/admin/...`, gated to administrators) — 38
  routes total.
- Frontend: TypeScript models, an API client, a domain service layer
  (`productCatalog.ts` — price formatting, default-variant selection,
  stock/sale logic), and 7 data-fetching hooks. No pages/UI yet — this is
  the foundation the next sprint's storefront consumes.
- Larastan (PHPStan, level 5) added as a static-analysis quality gate.
- Seeded MVP catalog: 1 category, 1 product ("Smisul Original"), 4
  variants with bulk-discount pricing.
- ~100 new backend tests (Unit: models, services with mocked
  repositories; Feature: repository query correctness, public API,
  admin CRUD + authorization).

### Fixed

- Eloquent's alphabetical pivot-table-naming convention would have
  guessed `product_promotion`/`category_promotion`; the actual migrations
  use `promotion_product`/`promotion_category`. Fixed by specifying pivot
  table names explicitly in the `belongsToMany()` relations.
- Laravel's `ResourceResponse` auto-returns HTTP 201 whenever the wrapped
  model was just inserted — surfaced as a wrong status code on the
  idempotent "set price" `PUT` endpoint on its first call. Fixed by
  forcing 200 explicitly.
- Retroactively fixed a Sprint 1 gap PHPStan caught: Larastan doesn't
  infer enum/datetime types through Laravel 11+'s `casts(): array` method
  style without explicit `@property` docblocks (affected `User`,
  `UserResource`, and all Sprint 2 models — all now annotated).

## Sprint 1 — Authentication & User Management (2026-07-02 – 2026-07-03)

### Added

- Full authentication flow: register, login/logout (with remember-me),
  forgot/reset password, email verification (signed links pointing at the
  frontend so the user never leaves the SPA) — backed by Form Requests,
  API Resources, Services, a Policy, and named rate limiters per endpoint.
- Two roles, `customer` (default, public-registerable) and
  `administrator` (seed-only — the `role` column is never mass-assignable).
  `AdminSeeder` reads `ADMIN_EMAIL`/`ADMIN_PASSWORD` from `.env` with no
  hardcoded fallback.
- Extended user profile: first/last name, phone, newsletter subscription,
  marketing consent, GDPR consent (timestamped).
- Sanctum configured for SPA cookie/session authentication (not Bearer
  tokens) — `EnsureFrontendRequestsAreStateful`, CORS with credentials,
  CSRF cookie flow.
- Matching React frontend: pages for every flow (Register, Login, Forgot/
  Reset Password, Verify Email, Profile), an API client
  (`src/api/client.ts`) wired for Sanctum's CSRF dance, auth state via
  `AuthContext`, and route guards (`ProtectedRoute`/`GuestRoute`).
- 55 backend tests (Unit + Feature) covering the above.

### Fixed

- `AuthService::login()`/`logout()` threw an uncaught `RuntimeException`
  (→ HTTP 500 with a full stack trace) when a request wasn't recognized
  as coming from a trusted frontend origin. Now returns a clean 400.
- `RegisterRequest`'s validation message for `gdpr_consent` read "The gdpr
  consent field must be accepted" — added a custom attribute label.
- Removed Laravel's default Blade+Vite scaffold (`resources/js`,
  `resources/css`, `welcome.blade.php`, `vite.config.js`, an unused
  `package.json`) — dead weight from the framework skeleton, since the
  real frontend is the separate `/frontend` SPA.

## Sprint 0 — Bootstrap (2026-07-02)

### Added

- Repository skeleton: `backend/` (Laravel 12, PHP 8.4), `frontend/`
  (React + TypeScript + Vite), plus `docs/`, `prompts/`, `scripts/`,
  `storage/`, `checklists/`, `templates/`, `research/`.
- Backend connected to a real local MySQL 9.x database (not SQLite);
  Laravel Pint configured and passing.
- Frontend configured with TypeScript (strict), Bootstrap 5, oxlint
  (chosen over ESLint for speed), and a dev-server proxy setup later
  replaced in Sprint 1 once the real CORS-based API client existed.
- Root `README.md` (setup instructions), `LICENSE` (proprietary), and
  `.gitignore` covering `.env`, `vendor/`, `node_modules/`, and build
  output across all three ignore-file scopes (root/backend/frontend).

## Stabilization pass (2026-07-03)

Baseline cleanup before Sprint 3, no new functionality:

- Removed the unused `laravel/sail` dependency (no Docker setup exists in
  this project) and Laravel's default scaffold tests
  (`tests/{Feature,Unit}/ExampleTest.php`), which tested nothing about
  this application.
- Eliminated duplicate validation-rule arrays across `Store`/`Update` Form
  Requests (`Product`, `ProductVariant`, `Category`) by having the
  `Update` variant extend `Store` and override only what differs —
  matching the pattern already used for `Promotion`.
- Eliminated duplicate attribute-mapping code in `ProductService`,
  `CategoryService`, `ProductVariantService`, and `PromotionService` by
  extracting shared private helpers.
- Extracted a shared `FormField` component for the label+input+error
  markup repeated across all 6 frontend auth/profile forms.
- Verified: Pint, PHPStan (0 errors), the full backend test suite (149
  tests), frontend build, and frontend lint (oxlint) all pass; `.env`,
  `vendor/`, `node_modules/`, and Laravel's `storage/` runtime files are
  all correctly git-ignored; no debug statements (`dd()`, `dump()`,
  `console.log()`, etc.) exist anywhere in the codebase.
