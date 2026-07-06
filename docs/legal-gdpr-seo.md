# SEO, Legal & GDPR foundation

## ⚠️ Legal copy is placeholder — lawyer review required before production

Every legal document seeded by `LegalDocumentSeeder` (Общи условия,
Политика за поверителност, GDPR Политика, Право на отказ от договора,
Политика за бисквитки, Политика за доставка, Политика за връщане и
рекламации) is realistic-**shaped** Bulgarian placeholder copy: it follows
the section structure a real document would have, but every
company-specific fact (`[Юридическо име на дружеството]`, `[ЕИК номер]`,
`[адрес]`, etc.) is a bracketed placeholder, and no clause should be
treated as final or legally binding. The same applies to the contact
details on `ContactPage.tsx` (`frontend/src/content/copy.ts`'s `contact`
export).

**Before production: a qualified lawyer must review and replace this
copy.** That review was explicitly out of scope for this sprint.

## Legal documents architecture

`LegalDocument` rows are versioned (see `LegalDocumentService`):
publishing a new version inserts a new row and flips `is_current`, never
mutating an already-accepted row — so a past order's
`OrderLegalAcceptance` always points at the exact version the customer
actually saw.

`LegalDocumentType` has 7 cases. Only 5 (`requiredAtCheckout()`) are
presented as checkout checkboxes — `ShippingPolicy` and `ReturnsPolicy`
are informational-only public pages, added this sprint without changing
checkout's existing 5-checkbox behavior. Every current document (all 7)
is listed at `GET /api/v1/legal-documents` and readable individually at
`GET /api/v1/legal-documents/{slug}` — this is what the footer links and
`/legal/:slug` pages use.

## GDPR consent audit log

`Consent` is a new, append-only, general-purpose audit table — **distinct
from** `OrderLegalAcceptance`, which continues to handle order-time
Terms/Privacy/etc. acceptance exactly as before. `Consent` covers what
`OrderLegalAcceptance` never did: account-level consent (registration,
profile updates) and cookie-banner consent.

Every write is an insert (`ConsentService::record`/`recordMany`), never
an update — "current state" for a subject+type is just its most recent
row (`ConsentService::currentFor`). Recorded automatically as a side
effect of:

- `AuthService::register()` — logs Terms/Privacy (always `true`, since
  `gdpr_consent` is a required checkbox) and Marketing/Newsletter
  (whatever the registration form submitted).
- `ProfileService::updateProfile()` — logs Marketing/Newsletter **only
  when those specific fields are present** in the update payload, so a
  partial profile edit (e.g. just the phone number) is never misread as
  a fresh consent decision.
- `ConsentController::storeCookiePreferences()` — logs all 4 cookie
  categories on every banner/preferences-modal submission.

**Known limitation:** `GET /api/v1/consent/cookies` can't distinguish
"never decided" from "explicitly rejected everything" — both shapes look
identical (`{necessary: true, analytics: false, marketing: false,
preferences: false}`). The cookie banner's visibility is therefore driven
by a local `localStorage` flag (`smisul_cookie_consent`), not by this
endpoint — see `CookieConsentContext.tsx`. The backend call is the audit
trail; it's fire-and-forget and never blocks the UI.

## Cookie consent (frontend)

- `CookieConsentProvider` (`frontend/src/context/CookieConsentContext.tsx`)
  wraps the app inside `AuthProvider` in `main.tsx`, so it can tell
  authenticated users (traced by session) from guests (traced by a
  client-generated UUID persisted in `localStorage`, see
  `services/guestConsentId.ts` — mirrors the existing guest-cart-token
  pattern).
- `CookieBanner` shows whenever no local decision exists yet; offers
  Accept all / Necessary only / Customize.
- `CookiePreferencesModal` is mounted independently in `PublicLayout` (not
  nested inside the banner) so it stays reachable from the footer's
  "Настройки на бисквитките" link even after the banner is gone for good.
- Categories: `necessary` (always on, not a real toggle), `analytics`,
  `marketing`, `preferences`.
- **No analytics/marketing scripts are loaded anywhere in this codebase**
  — there is nothing to gate yet. When a real analytics/marketing
  integration is added, it must check `useCookieConsent().choices` before
  loading, not just present the banner cosmetically.

## SEO

- `Seo.tsx` now supports Twitter Card meta (`summary_large_image` when an
  `ogImage` is given, `summary` otherwise) alongside its existing
  title/description/canonical/Open Graph tags.
- `jsonLd` now accepts a single object **or an array**, rendered as
  multiple `<script type="application/ld+json">` tags — used to combine a
  page's own schema (e.g. `Product`) with a `BreadcrumbList` built from
  the same items passed to `<Breadcrumbs>` (see
  `services/structuredData.ts::buildBreadcrumbJsonLd`, wired into
  `ProductPage`, `CategoryPage`, `LegalPage`, `AboutPage`, `ContactPage`).
- `Organization` and `WebSite`+`SearchAction` schema
  (`services/structuredData.ts`) are emitted **only on the homepage**,
  per Google's own guidance for the sitelinks search box — not site-wide.
- Same caveat as before this sprint: this is all written via
  `useEffect` DOM manipulation, so it works for crawlers that execute
  JavaScript (Googlebot does) but not link-preview bots that read only
  the initial HTML. Closing that gap needs SSR/prerendering, which is out
  of scope here.

## Sitemap & robots.txt

- `GET /sitemap.xml` (`backend/routes/web.php` — deliberately outside
  `routes/api.php`'s `/api` prefix) is generated dynamically by
  `SitemapService`: home, search, active categories, published products,
  every *currently-published* legal document (a type with no published
  version is skipped, rather than putting a 404 in the sitemap), plus
  static `/about` and `/contact` entries. All URLs point at the
  **frontend's** domain (`config('app.frontend_url')`), since that's what
  search engines actually need to crawl.
- `backend/public/robots.txt` is hardened to `Disallow: /` — this backend
  is an API-only domain with nothing worth indexing.
- `frontend/public/robots.txt` is a static file disallowing
  `/admin`, `/cart`, `/checkout`, `/profile`, `/api`, with a `Sitemap:`
  directive.

### Production configuration still needed

1. **Sitemap reverse-proxy rule.** The frontend (`localhost:5173` in dev)
   and backend (`localhost:8000` in dev) are different origins even
   locally. `/sitemap.xml` is generated by the backend but must be
   reachable at `https://<real-domain>/sitemap.xml` for SEO purposes —
   production needs a reverse-proxy/rewrite rule forwarding that one path
   from the frontend's real domain to the backend.
2. **`frontend/public/robots.txt`'s `Sitemap:` line** currently points at
   `http://localhost:5173/sitemap.xml` — update it to the real production
   domain once deployed (see the `TODO(production)` comment in that
   file).
3. **`FRONTEND_URL`** (backend `.env`) must be set to the real production
   frontend domain — `SitemapService` builds every URL from it.
