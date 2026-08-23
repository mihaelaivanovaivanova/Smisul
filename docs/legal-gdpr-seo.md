# SEO, Legal & GDPR foundation

## ⚠️ Legal copy has real company data, but still needs a licensed lawyer's sign-off before launch

Every legal document seeded by `LegalDocumentSeeder` — now 4, not 6 (see
"Legal documents architecture" below): Общи условия, Политика за
поверителност и бисквитки (includes GDPR disclosures and the former
Cookie Policy), Право на отказ, връщане и рекламации (includes the
former Returns Policy), Политика за доставка — now carries the **real**
merchant identity (`„ФИЛЧЕВ УЕБ“ ЕООД`, ЕИК 208699419, Varna address,
contact@smisul.bg, +359 876 291 040) — not bracketed placeholders. As of
2026-08-21 the content was reviewed pass-by-pass against the site's
actual live behavior (COD scoped to BOX NOW only, the BOX NOW free-
shipping launch promo through 2026-09-30, merchant-initiated order
cancellation, refund-method fallback for cash-paid orders, locker
pickup-date semantics for withdrawal) and updated where it had drifted
from what the code actually does. It is **not** a substitute for review
by a licensed Bulgarian attorney — no clause here should be treated as
final until that review happens, especially around VAT-registration
status (currently "not registered", confirmed 2026-08-21 — reconfirm
before launch since it changes several clauses if it flips) and the
mandatory EUR/BGN dual-pricing window under the euro-introduction law
(verify the actual adoption date against official sources; this doc
assumes it's still within the dual-display period).

The same real merchant identity should also appear on `ContactPage.tsx`
and the footer (`frontend/src/content/copy.ts`'s `contact` export /
admin-configured `company_name`/`company_id`/`store_email` settings) —
**keep these in sync manually.** `LegalDocument` content is static
versioned text, not templated from `Settings`, so if the merchant
identity is ever changed in Settings → General, the legal documents
won't update automatically and will silently drift from the footer.

## Legal documents architecture

`LegalDocument` rows are versioned (see `LegalDocumentService`):
publishing a new version inserts a new row and flips `is_current`, never
mutating an already-accepted row — so a past order's
`OrderLegalAcceptance` always points at the exact version the customer
actually saw.

`LegalDocumentType` has 4 cases (reduced from 6 on 2026-08-21 — see
below). Only 3 (`requiredAtCheckout()`: ToS, Privacy, Right of
Withdrawal) are presented as checkout checkboxes — `ShippingPolicy` is
the one remaining informational-only public page, deliberately kept
separate (not folded into ToS) because its content (carrier list, promo
pricing/dates) changes far more often than actual legal terms, and
merging it into a checkout-required document would trigger a mass
"please re-accept" notification to every account holder
(`NotifyAccountHoldersOfLegalDocumentUpdate`) on every such change. Every
current document (all 4) is listed at `GET /api/v1/legal-documents` and
readable individually at `GET /api/v1/legal-documents/{slug}` — this is
what the footer links and `/legal/:slug` pages use.

**GDPR is folded into the Privacy Policy, not a separate document.**
There used to be a distinct `GdprPolicy` type, but a GDPR-compliant
Privacy Policy already covers everything that document did (lawful
basis, data subject rights, retention, international transfers,
automated decision-making) — keeping them apart was redundant, so the
GDPR-specific sections were merged into `PrivacyPolicy`'s seeded content
and the separate type/slug (`gdpr-policy`) was removed.

**2026-08-21: Cookie Policy merged into Privacy Policy, Returns Policy
merged into Right of Withdrawal.** Same reasoning as the GDPR merge
above — each pair answered the same underlying customer question
(data/cookies; money-back/complaints) across two documents instead of
one, and `CookiePolicy`/`ReturnsPolicy` were removed as enum cases
entirely (not just deprecated) since nothing was in production yet. One
side effect worth knowing: merging Returns into Right of Withdrawal
(a `requiredAtCheckout()` type) means the legal-guarantee-of-conformity
disclosure is now part of the mandatory checkout acknowledgment, closing
a real pre-contract-disclosure gap under чл. 47, ал. 1, т. 12 ЗЗП that
existed while Returns was a separate, checkout-optional document. See
`LegalDocumentType`'s per-case docblocks for the full reasoning, and
`legal_documents_review_2026_08` in project memory for the migration
details (orphaned `legal_documents`/`order_legal_acceptances` rows from
the old types had to be cleaned up in dev — non-issue for a fresh
`migrate:fresh --seed`, only relevant because this DB pre-dated the
change).

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
