# Funnel Mode — End-to-End QA Test Scenarios

**Scope:** The single-product "funnel mode" landing page (`FunnelLandingPage.tsx`, rendered at `/` when `funnel_configs.is_enabled = true`), every component it mounts, its lead-capture and analytics side effects, its admin configuration surface (`/admin/funnel`), and the full purchase journey it funnels visitors into (cart → checkout → payment → confirmation), since a landing page that doesn't convert into a completed order isn't actually "working."

**Out of scope:** the non-funnel homepage (`HomePage.tsx`), catalog/category browsing, and admin areas unrelated to the funnel (products, promotions, orders admin) except where the funnel depends on them (product/variant data, shipping/payment services).

**Environment assumptions:** Tester has admin panel access and DB access (per request, DB verification steps are called out explicitly). Test against a non-production environment where destructive actions (toggling funnel off, deleting leads, placing real card charges) are safe. iCard should be tested against its sandbox/test environment unless explicitly instructed otherwise (see skill's Safety Rules — never test real payments without authorization).

**Ground truth verified in code as of 2026-08-30** (verify still current before relying on it — these are exactly the kind of claims this document tells you to re-check):
- `FunnelConfig::current()` defaults `is_enabled = true` on first-ever row creation (`backend/app/Models/FunnelConfig.php`) — a fresh install ships with funnel mode ON.
- Payment methods: **card only**. `PaymentMethod::active()` returns `[Card]`. Cash-on-delivery was implemented, briefly re-enabled for BOX NOW, then removed again (commit `Removed pay on delivery functionality`). The `CashOnDelivery` enum case still exists only so historical orders don't 500 on read — it must never appear as a selectable checkout option.
- Shipping carriers: **Speedy** and **BOX NOW** only. Econt was fully removed from the codebase and all legal docs.
- BOX NOW shipping is **free** (€0.00) while `now() <= config('services.shipping.box_now.free_shipping_promo_until')`, defaulting to **2026-09-30 23:59:59**. Today (2026-08-30) is inside the promo window — expect free BOX NOW shipping, with the boundary one month away.
- Dual EUR/BGN price display (`DUAL_PRICE_BGN` in `productCatalog.ts`) is currently **hardcoded `false`** — correct today since the legal dual-display obligation window (8 Aug 2025 – 8 Aug 2026) already ended, but it's a hardcoded boolean, not date-driven, so it doesn't self-correct if that legal window is ever reinstated or extended. Worth a content/compliance check, not a functional bug today.
- BOX NOW is pre-selected as the default shipping method once shipping methods load, but only once — never re-forces itself after the customer switches away.
- Funnel packages are always exactly 4 (`FunnelPackagesRequest`: `packages` must be `size:4`), each pointing at a variant of the single configured product.

---

## 1. Funnel Mode Toggle, Routing & Global Chrome

### FUN-101 — Fresh install defaults funnel mode ON
- **Priority:** P1
- **Preconditions:** A fresh database with no `funnel_configs` row (e.g. right after `migrate:fresh --seed`, or delete the single row manually in a test env).
- **Steps:** 1. Run migrations/seed. 2. Query `SELECT * FROM funnel_configs;`. 3. Visit `/` as a guest.
- **Expected:** Exactly one row exists with `is_enabled = 1`. `/` renders `FunnelLandingPage`, not `HomePage`.
- **DB check:** `funnel_configs` has a single row (this table must never have more than one — verify no code path can insert a second).

### FUN-102 — Admin toggle OFF reverts homepage
- **Steps:** 1. In `/admin/funnel`, disable funnel mode. 2. Visit `/` in a fresh tab/incognito (no stale SPA state). 3. Visit `/search`.
- **Expected:** `/` now renders the normal multi-category `HomePage`. `/search` is reachable and renders `SearchPage` (not redirected).
- **DB check:** `funnel_configs.is_enabled = 0`. Confirm via `admin_action_logs` that a `funnel.toggled` entry was recorded with the admin's user id and timestamp.

### FUN-103 — Admin toggle ON re-enables funnel
- **Steps:** Re-enable from `/admin/funnel`; reload `/` and `/search`.
- **Expected:** `/` shows the funnel page again; `/search` immediately redirects (`Navigate replace`) to `/`, and the browser history entry for `/search` is replaced, not pushed (Back from `/` shouldn't land back on a blank `/search`).

### FUN-104 — Toggle takes effect without a hard refresh for an already-open tab
- **Steps:** Open the storefront in Tab A. In Tab B (admin), toggle funnel mode. Return to Tab A, navigate internally (e.g. click logo) without a full reload.
- **Expected:** Behavior depends on whether settings are refetched on navigation vs. cached for the SPA session — verify what actually happens (does Tab A need a hard refresh to see the new mode, or does the settings context refetch?). Document the actual behavior; if it silently serves stale mode for the rest of the session, flag as a UX/staleness note, not necessarily a bug.

### FUN-105 — Only `/` is affected by the toggle; every other route is stable
- **Steps:** With funnel mode ON, directly navigate (typed URL / deep link) to `/products/:slug`, `/categories/:slug`, `/cart`, `/checkout`, `/about`, `/legal/:slug`.
- **Expected:** All render normally and are fully functional regardless of funnel mode — the funnel toggle must not gate anything except `/` and the `/search` redirect.

### FUN-106 — Navbar hides search input and "Favorites" link while funnel mode is ON
- **Steps:** With funnel mode ON, inspect the navbar as (a) guest, (b) logged-in customer, (c) admin viewing the storefront.
- **Expected:** The search `<form>` is not rendered at all (not just visually hidden — check DOM). The "Favorites" dropdown item is hidden for non-admin users. Confirm admin users' navbar behavior explicitly (code shows `!funnelModeEnabled && !isAdmin` gating the favorites item — verify what an admin sees, since they may see it even in funnel mode).
- **Regression risk:** If the settings fetch (`isSettingsLoading`) hasn't resolved yet, verify there's no flash of the search bar before it disappears (FOUC).

### FUN-107 — Navbar behavior restored when funnel mode is OFF
- **Steps:** With funnel mode OFF, check navbar again.
- **Expected:** Search form and Favorites link both present and functional as on the normal homepage.

### FUN-108 — `/search` guard redirect race with settings loading
- **Steps:** Simulate a slow settings API response (throttle network), then rapidly navigate to `/search` before settings resolve.
- **Expected:** `FunnelSearchGuard` renders `null` while `isLoading`, then redirects only after settings resolve as enabled. Verify no flash of `SearchPage` content before the redirect if funnel mode turns out to be enabled.

### FUN-109 — Direct deep link to `/search?q=...` while funnel mode is ON
- **Steps:** With funnel mode ON, paste a URL with a search query string directly into the address bar.
- **Expected:** Still redirected to `/` (query string is simply dropped) — confirm this doesn't error and doesn't leak the query into `/`'s own state.

---

## 2. Landing Page Load, Loading & Error States

### FUN-201 — Cold load happy path
- **Steps:** Clear cache/cookies. Load `/` fresh.
- **Expected:** A loading state renders first (`LoadingState`), then the full page renders once both settings and the product resolve. No layout shift once loaded (images have width/height attributes — spot-check).

### FUN-202 — Funnel product slug missing/unset in admin config
- **Preconditions:** In DB, set `funnel_configs.product_id = NULL` (or point it at a non-existent id).
- **Steps:** Visit `/` as a guest.
- **Expected:** Per `FunnelService::resolveProduct`, `product_slug` comes back `null`. Confirm what the frontend actually does: `fetchProduct` is never called (`funnelProductSlug` falsy → `Promise.resolve(null)`), so `product` stays `null` and the page should show the `ErrorState` branch (`error || !funnelContent || !product`). Verify a real, non-broken error message is shown, not a blank white page or a spinner stuck forever.
- **DB check:** confirms `resolveProduct()`'s graceful-null behavior actually reaches the frontend as intended.

### FUN-203 — Funnel product exists but is unpublished
- **Preconditions:** Set the configured product's `status` to something other than `Published`.
- **Steps:** Visit `/`.
- **Expected:** `FunnelService::resolveProduct()` filters on `ProductStatus::Published`, so this must behave identically to FUN-202 (graceful error, not a 500, not a broken partial render).

### FUN-204 — Funnel product has zero variants
- **Preconditions:** Configured product exists, published, but has no variants (or all variants inactive/unavailable).
- **Steps:** Visit `/`.
- **Expected:** `defaultVariant` resolves to `null`; `price` is `null`; `fromPrice`/`fromPriceLabel` are `null`. Verify every consumer of these (`HeroSection`, sticky bars, `PricingSection`'s fallback button) degrades gracefully instead of throwing — e.g. sticky bars should render nothing (`if (!fromPriceLabel) return null`), not a bar with a blank/undefined price.

### FUN-205 — Products API fails (500/timeout) after settings load successfully
- **Steps:** Simulate the `/products/:slug` (or whatever `fetchProduct` hits) endpoint failing.
- **Expected:** `useAsync`'s `error` state is set with the Bulgarian message "Продуктът не можа да се зареди."; `ErrorState` renders it. No console-uncaught promise rejections.

### FUN-206 — Reviews API fails or returns zero reviews
- **Steps:** (a) Force the reviews endpoint to error. (b) Force it to return `review_count: 0`.
- **Expected:** Per the code's explicit design ("deliberately not part of the page's loading/error gates"), a failed or empty reviews fetch must **not** break the rest of the page — it should just hide the hero rating line and the testimonials section content, while everything else on the page renders normally.

### FUN-207 — Public settings API fails (dispatch cutoff / BOX NOW badge toggle)
- **Steps:** Force `fetchPublicSettings` to fail.
- **Expected:** `dispatchCutoff` stays `null` → `DispatchPromise` renders nothing anywhere it's used (no crash). `publicSettings?.box_now_badge_enabled !== false` evaluates `true` when `publicSettings` is `undefined` (since `undefined !== false`) — confirm the BOX NOW badge **still shows by default** when settings fail to load, matching the documented "defaults to shown while loading" intent, not accidentally hidden.

### FUN-208 — Hash-anchor navigation on real page load (`/#core-benefits` etc.)
- **Steps:** Type `https://<site>/#pricing` directly into the address bar (a real navigation, not an in-app link click) and load it.
- **Expected:** Page loads, then smooth-scrolls to `#pricing` once content/settings have finished loading (the effect explicitly waits on `settingsLoading`/`isLoading`). Try this for every anchor id actually present: `#pricing-early` is NOT a valid target per the doc comment (non-anchor placement) — confirm nothing targets it externally; `#pricing`, `#delivery-payment-returns`, `#faq`, `#newsletter` should all work.

### FUN-209 — `#usage-guide` pseudo-anchor
- **Steps:** From `HowToUseSection`, click "Виж пълните инструкции" (or whatever its current label is). Separately, load `/#usage-guide` directly as a fresh navigation.
- **Expected:** Both cases scroll to `#faq` and open the FAQ item whose question matches `/как се използва/i` (case-insensitive, Cyrillic). If no FAQ item matches that regex (e.g. admin edited/retitled the question), the code falls back to index `0` — verify this fallback doesn't silently open a *wrong, unrelated* FAQ answer that confuses the visitor. Consider this a content-drift trap: retitling the usage FAQ question breaks this silently with no error anywhere.

### FUN-210 — Scroll-reveal animation gating
- **Steps:** (a) Load normally and scroll through the whole page slowly. (b) Enable OS/browser "reduce motion" and reload.
- **Expected:** (a) Each targeted element (`.funnel-photo`, `.funnel-usecase-card`, etc.) fades/rises into view once, staggered within its section, and never re-hides on scroll-back (uses `unobserve` after reveal — confirm no re-trigger). (b) With reduced-motion active, everything is visible immediately with no animation and no elements stuck invisible (the effect returns early and skips adding `.funnel-reveal` entirely — confirm elements aren't stuck at `opacity:0` from a CSS rule that assumed JS would always add the reveal class).
- **Regression risk:** hero and package cards are explicitly exempt from this — confirm they're always visible on load, never accidentally caught by a class-based selector overlap.

---

## 3. Hero Section

### FUN-301 — Hero renders admin-configured copy and correct default-variant price
- **Steps:** In `/admin/funnel`, edit `funnel.hero` content (title, subtitle, `cta_primary`). Save. Reload `/`.
- **Expected:** New copy appears verbatim, including any Cyrillic special characters, line breaks, or embedded brand wordmark rendering (check `renderWithBrandWordmark` usage elsewhere doesn't apply here unless hero copy also uses it).

### FUN-302 — Hero "from price" reflects the cheapest package, not the default variant, when packages exist
- **Steps:** Configure 4 packages with prices e.g. 3.99 / 10.99 / 17.49 / 32.99 (1/3/5/10-pack). Load `/`.
- **Expected:** `fromPrice` = the *minimum* across `packageOffers`, i.e. the 1-pack's €3.99, even though `defaultVariant`'s own price might differ. Verify hero, sticky bars, and the mid-page CTA all use this same computed minimum and stay consistent with each other.

### FUN-303 — Hero "from price" falls back to default variant price when no packages configured
- **Preconditions:** `funnel_configs.packages = []` or product has no matching variants for the configured packages.
- **Steps:** Load `/`.
- **Expected:** `fromPrice` = `price` (default variant's own price), not `null`/broken. Every "from X €" label across the page reflects this fallback consistently.

### FUN-304 — Dispatch promise inside hero — cutoff boundary behavior
- **Steps:** Set `same_day_dispatch_cutoff` (admin → General settings) to a time a few minutes in the future. Load the page and watch it live across the cutoff moment (or set system/browser clock near the boundary).
- **Expected:** Before cutoff on a weekday: shows "Поръчай до HH:MM ч. — изпращаме още днес (остават X ч Y мин)" and the remaining time visibly ticks down (component re-renders every 30s). Exactly at/after cutoff: the whole line disappears (not "0 мин remaining", it vanishes). On Sat/Sun: never shows regardless of time. With `cutoff` unset/empty: never shows.
- **Edge case:** cutoff value that doesn't match `HH:MM` format (e.g. corrupted admin input like `"14"` or `"14:0"` or `"25:00"`) — confirm the regex `^(\d{1,2}):(\d{2})$` rejects malformed values gracefully (renders nothing) rather than crashing or showing "NaN ч NaN мин". Try `"9:5"` (should match `\d{1,2}:\d{2}`? — note `\d{2}` requires exactly 2 digits, so `"9:5"` should NOT match; confirm nothing renders, not a crash).
- **Timezone note:** code comment assumes visitor's local clock ≈ Europe/Sofia warehouse time. Test with a browser clock/timezone set to something far off (e.g. US Pacific) to confirm the promise shows *wrong* dispatch timing in that case — document as a known limitation, not necessarily a bug to fix, but worth flagging for visitors traveling abroad.

### FUN-305 — Hero CTA scroll target
- **Steps:** Click the hero's primary CTA button.
- **Expected:** Smooth-scrolls to `#pricing` (confirm it's the numbered section, not `#pricing-early`, even though `#pricing-early` is physically closer).

### FUN-306 — Hero is exempt from scroll-reveal and always fully visible immediately
- **Steps:** Hard-reload directly (not via SPA nav) and check the hero content's opacity/transform on first paint.
- **Expected:** No fade-in delay — content is immediately visible (per the explicit "nothing above the fold may start hidden" design intent).

---

## 4. Early Pricing Section ("pricing-early", right after Hero)

### FUN-401 — Early pricing box omits subtitle and sales note
- **Steps:** Compare the `id="pricing-early"` instance against the numbered `id="pricing"` instance further down.
- **Expected:** Early instance shows title + `DispatchPromise` + package cards/CTA only — no subtitle paragraph, no sales-note paragraph underneath. The numbered instance shows both.

### FUN-402 — No duplicate-DOM-id collisions between the two Pricing instances
- **Steps:** Inspect rendered DOM with devtools; search for duplicate `id` attributes, duplicate radio-group `name`s (if package selection ever uses radios), duplicate `aria-labelledby` targets.
- **Expected:** Zero duplicate ids anywhere on the page (`pricing-early` vs `pricing` are the only two named ids for this section pair; every inner element must not itself hardcode an id used twice).

### FUN-403 — Adding an item from the *early* pricing box vs. the *numbered* pricing box behaves identically
- **Steps:** From a fresh cart, add a package from `pricing-early`. Reset cart. Add the *same* package from the numbered `#pricing` section.
- **Expected:** Identical resulting cart line item, identical `trackFunnelAddToCart` analytics payload, identical navigation to `/cart`. The duplication must be purely visual/positional, not functionally divergent.

---

## 5. Use Cases / What Is Miswak / Core Benefits / How To Use / Science / Skepticism / Comparison Sections

*(These are largely static/admin-editable content sections with lower interaction surface — test as a group, calling out the ones with real interactive logic separately.)*

### FUN-501 — Admin content edits propagate correctly per section
- **Steps:** For each of `intro` (What Is Miswak), `why` (Core Benefits), `science`, `awareness` (Skepticism/Honesty), `comparison` — edit title/body/paragraphs/list items in `/admin/funnel`, save, reload the public page.
- **Expected:** Each section reflects the new copy exactly, including: empty-string fields (does the section render an empty `<h2>` or hide gracefully?), very long text (does it overflow its card/container or wrap correctly at each viewport?), and copy containing `<`, `>`, `&` characters (confirm proper escaping — no raw HTML injection, no broken rendering, no XSS if content is ever rendered via `dangerouslySetInnerHTML` anywhere in these components — grep for it and flag as high-priority if found unescaped).

### FUN-502 — `UseCasesSection` is fixed copy, not admin-editable
- **Steps:** Attempt to find/edit "use cases" content in `/admin/funnel`'s content editor.
- **Expected:** Per the code comment, `funnelUseCases` is fixed frontend copy (`content/copy.ts`) — confirm there is genuinely no admin control for it (so this isn't accidentally a missing/broken save action, but an intentional design choice) and that this matches user/business expectations. If the business now wants this editable, that's a product gap, not a bug — but worth surfacing.

### FUN-503 — `HowToUseSection` video rendering
- **Steps:** With the funnel product configured to have (a) one product video, (b) multiple videos, (c) zero videos.
- **Expected:** (a)/(b) render correctly with working playback controls, sensible poster/thumbnail, and no autoplay-with-sound (check autoplay policy compliance — should be muted-autoplay or click-to-play, never audible autoplay). (c) Section still renders its text content sensibly without a broken empty video slot.

### FUN-504 — `HowToUseSection`'s "Виж пълните инструкции" link uses the FAQ's own PDF attachment, live
- **Steps:** In `/admin/funnel`, upload/replace the usage-FAQ item's PDF attachment. Reload the funnel page (without touching HowToUseSection's own config).
- **Expected:** The CTA's href updates automatically to the new PDF URL — confirmed live binding, not a stale copy. If the FAQ item's `attachment_url` is empty, the CTA should fall back sensibly (code shows `|| undefined` — verify what the button/link does when `usageGuidePdfUrl` is `undefined`: hidden entirely, or a disabled/dead link? A dead link with no `href` is a real bug.)

### FUN-505 — `ComparisonSection` CTA and "from price" consistency
- **Steps:** Check `ComparisonSection`'s CTA button label and price nudge against the Hero's.
- **Expected:** Both use the exact same `fromPrice`/`ctaLabel` props — confirm no drift (e.g. one shows a stale/cached price after a package price change while the other updates).

### FUN-506 — Content sections render correctly with zero configured content (brand-new install before any admin edits)
- **Steps:** On a fresh seed, before any admin touches `/admin/funnel` content, load `/`.
- **Expected:** `FunnelSeeder.php` should have seeded real defaults for every section — confirm none of the 12 `FUNNEL_SECTIONS` keys render blank/broken. If a section key is missing from the seeder entirely, `FunnelContentService::all()` returns `[]` for it — check every consuming component tolerates an empty content object without crashing (optional chaining / fallback text).

---

## 6. Testimonials / Reviews Section

### FUN-601 — Review summary hero line only shows with real reviews
- **Steps:** Configure the funnel product with (a) 0 reviews, (b) 1 review, (c) many (>10) reviews.
- **Expected:** (a) `reviewSummary` is `null` (code explicitly checks `review_count > 0`), hero rating line and desktop sticky bar's rating both hidden. (b)/(c) shown correctly with accurate average and count.

### FUN-602 — Testimonials carousel shows up to page-1 reviews (not capped at 3)
- **Steps:** Seed 8–10 reviews for the funnel product, sorted by "helpful". Load the page.
- **Expected:** All of page 1 (per `ReviewService::listForProduct`'s default page size) appear in the scrollable carousel, not just the first 3. Confirm the carousel actually scrolls (touch-drag on mobile, arrow/scroll on desktop) and doesn't clip content.

### FUN-603 — Review content displays real user-submitted text safely
- **Steps:** Include a review with special characters, very long body text, emoji, and a review with a 1-star rating alongside 5-star ones.
- **Expected:** All render without breaking layout; a low rating isn't filtered out or hidden by the funnel page (only sorted by "helpful" — confirm it isn't secretly filtering by rating threshold, which would be a trust/authenticity issue).

### FUN-604 — Product schema (`productJsonLd`) aggregate rating matches visible reviews
- **Steps:** View page source / use a rich-results testing tool against the rendered JSON-LD `<script>` tag.
- **Expected:** `aggregateRating.ratingValue`/`reviewCount` exactly match what's shown in the visible summary; `review[]` entries match the top testimonials shown; `offers.lowPrice`/`highPrice` match the real variant price range; `availability` correctly reflects `InStock`/`OutOfStock` based on actual inventory.

### FUN-605 — FAQ schema matches visible FAQ
- **Steps:** Compare `faqJsonLd.mainEntity` against the rendered FAQ accordion questions/answers, including after an admin edits an FAQ item.
- **Expected:** Exact match, including Cyrillic text encoding (no mojibake in the JSON-LD).

---

## 7. Pricing Section & Package Offers (core conversion surface)

### FUN-701 — Featured card is always the 5-pack, regardless of package order or count
- **Steps:** In `/admin/funnel`, configure the 4 packages in a shuffled order (e.g. 10-pack first, 1-pack last). Reload public page.
- **Expected:** The card with `pack_size === 5` is visually featured/dominant regardless of its position in the array — confirmed via `featuredIndex = offers.findIndex(...)`. If no package has `pack_size === 5` (e.g. admin swapped it for a different size), `featuredIndex` is `-1` — confirm no card ends up incorrectly styled as "featured" (index `-1` should never equal any real `index`) and that the page doesn't look broken with *no* featured card.

### FUN-702 — Savings percentage is computed live from the real 1-pack price, never fabricated
- **Steps:** Note current 1/3/5/10-pack prices and compute expected savings % by hand: `(1 - packPrice/packSize/singlePrice) * 100`, rounded. Compare to each card's displayed `-X%` badge.
- **Expected:** Exact match for 3/5/10-pack cards. The 1-pack card itself shows **no** savings badge (`variant.pack_size > 1` guard). If the computed percentage is `0` or negative (e.g. admin misconfigures a bundle to cost *more* per unit than the single stick), confirm the badge is hidden (`savingsPercent > 0` guard) rather than showing "-0%" or a nonsensical negative discount.

### FUN-703 — No 1-pack configured at all
- **Steps:** Configure funnel packages using only 3/5/10-pack variants (no `pack_size === 1` among them) — if the `size:4` validation still requires exactly 4 packages, use e.g. two different 3-packs or another combination that satisfies validation without a 1-pack.
- **Expected:** `computeSavingsPercent` returns `null` for every package (no `singleStickPrice` to compare against) — confirm **zero** savings badges render anywhere rather than a broken/`NaN%` badge.

### FUN-704 — Per-unit price line
- **Steps:** For each multi-pack card, verify the "X € per unit" line equals `price.amount / pack_size`, correctly rounded/formatted, in the correct currency symbol.
- **Expected:** Exact match; not shown at all for the 1-pack (`pack_size > 1` guard).

### FUN-705 — Package images map correctly to pack size, missing image fallback
- **Steps:** Configure a package whose variant's `pack_size` is something other than 1/3/5/10 (e.g. a future 7-pack), if the admin UI even allows it.
- **Expected:** `packageImages[variant.pack_size]` is `undefined` for unmapped sizes → `hasImage` is `false` → card renders in the "no-image" layout (`funnel-package-card--no-image` class), not a broken `<img>` with an empty `src`.

### FUN-706 — Low-stock badge honesty
- **Steps:** Set a package variant's inventory to (a) in stock, plenty available, (b) in stock but below `low_stock_threshold` with `available_quantity > 0`, (c) `available_quantity = 0` but still technically `is_in_stock` (edge state — verify whether backend can even produce this combination), (d) fully out of stock.
- **Expected:** (a) no stock badge. (b) shows "Остават само X броя" (or equivalent lowStock copy) with the *exact real* `available_quantity`. (c) per the triple guard (`is_in_stock && is_low_stock && available_quantity > 0`), this combination should NOT show the badge (quantity is 0) — confirm no "Остават само 0 броя" ever renders, since that's a real UX inconsistency if the backend can produce it. (d) CTA replaced entirely with "Изчерпано"/out-of-stock text, no Add-to-Cart button rendered at all (`AddToCartButton` returns `null` when `!canAdd`).

### FUN-707 — Out-of-stock package: no crash, no dead click target
- **Steps:** Make one of the 4 packages out of stock while the other 3 remain purchasable.
- **Expected:** That one card shows the muted "Изчерпано" text instead of a button; the other 3 remain fully functional. Confirm the out-of-stock card doesn't retain a lingering clickable element that silently does nothing.

### FUN-708 — Add-to-cart from a package card navigates to `/cart` (funnel-only behavior)
- **Steps:** Click "Add" on any in-stock package.
- **Expected:** Item added with quantity exactly 1 (packages hide the quantity stepper — `hideQuantity`), `trackFunnelAddToCart(price.amount, price.currency)` fires with the *package's* price (not the base variant's unit price), then immediate navigation to `/cart`. Confirm this differs intentionally from the regular product-page `AddToCartButton` (which stays in place) — re-verify this funnel-only redirect doesn't also fire on the product page instance of the same component.

### FUN-709 — Rapid double-click / duplicate-add protection on package CTA
- **Steps:** Double/rapid-click a package's Add button before the request resolves.
- **Expected:** `isPending` disables the button after the first click — confirm the *second* click physically cannot register (button truly disabled, not just visually) and the cart doesn't end up with quantity 2 or two separate line items from one intended click. Also test on a throttled/slow network to widen the window where a race could occur.

### FUN-710 — Add-to-cart error surfaces without losing page state
- **Steps:** Force the cart API to return an error (e.g. stock just sold out server-side between page load and click, or a 500).
- **Expected:** `error` text shown inline under that specific card's CTA (via `getErrorMessage`), in Bulgarian, without a page-wide crash; other cards remain independently functional; retry works.

### FUN-711 — Package variant becomes stale (admin deletes/unpublishes the variant after configuring it)
- **Preconditions:** Configure a funnel package pointing at variant X, then unpublish/delete variant X (or the whole product) from Products admin, without updating `/admin/funnel`.
- **Steps:** Reload `/`.
- **Expected:** Per `resolvePackages()`'s filter, that dangling package silently drops out of the *public* payload. If **all 4** drop out (or the whole product becomes invalid), `packageOffers` is `[]` and the page should fall back to the single default-variant `AddToCartButton` — confirm this fallback actually renders and isn't itself broken by the same stale reference.
- **Admin check:** In `/admin/funnel`, the *admin* payload (`adminPayload()`) intentionally still returns the raw stale `product_id`/`packages` as stored — confirm the admin UI actually surfaces this discrepancy somehow (e.g. shows the broken variant reference) rather than silently showing a config that looks fine to the admin while being broken for every visitor.

### FUN-712 — Fallback single-variant button (`packageOffers.length === 0`)
- **Steps:** Force zero valid packages (see FUN-711) with a valid default variant still present.
- **Expected:** A single `AddToCartButton` renders with `fallbackCtaLabel` (from `final_cta.cta`), quantity hidden, and `onAdded` = `handleFallbackAddToCart`, which tracks `trackFunnelAddToCart` using the *default variant's* price and then **navigates to `/cart`** as well (confirm — `handleFallbackAddToCart` explicitly calls `navigate('/cart')`, same as the package path, so behavior should be consistent even in the degraded state).

---

## 8. Delivery / Payment / Returns Trust Section

### FUN-801 — Trust items accurately reflect current business rules (no false advertising)
- **Steps:** Compare each `trust_items` line against actual system behavior: "Доставка 1-2 работни дни" vs. real shipping method `estimated_delivery`; "Сигурно плащане с карта" vs. `PaymentMethod::active()` (should be exactly `[Card]` — confirm **no** COD-related trust item exists anywhere on the page, since COD was removed); "30 дни право на връщане" vs. the actual Returns/Refund legal document's stated window; "100% гаранция за качество" — flag as a content/legal risk if there's no documented policy backing an unconditional quality guarantee (the seeder's own comment already flags this history — confirm current legal docs actually support the claim, don't just assume it's fine because it's "back by request").
- **Severity if violated:** High — false or stale claims here are a direct чл. 68д ЗЗП / EU UCPD consumer-protection exposure, exactly like the historical free-shipping badge issue.

### FUN-802 — Section anchor target for BOX NOW badge link
- **Steps:** Click the floating BOX NOW badge (see section 12).
- **Expected:** Scrolls precisely to this section (`#delivery-payment-returns`); confirm the id actually exists on this section's root element (a rename here would silently break the badge's only functional purpose).

---

## 9. FAQ Section

### FUN-901 — Accordion open/close behavior
- **Steps:** Click each FAQ question in turn.
- **Expected:** Exactly one item open at a time (`activeFaqIndex` is a single index, not a Set) — clicking an already-open item closes it (`activeFaqIndex === index ? null : index`); clicking a different item closes the previous and opens the new one. Verify smooth height animation with no content clipping mid-transition, and correct `aria-expanded`/`aria-controls` for screen readers.

### FUN-902 — FAQ attachment (PDF) upload and replacement flow (admin)
- **Steps:** In `/admin/funnel`, upload a PDF for an FAQ item. Save the FAQ section. Reload public page and click through to the attachment.
- **Expected:** File uploads via `FunnelFaqAttachmentUploadRequest`, returns a public URL; **the admin must click Save on the FAQ section afterward for it to take effect** (per the controller's own doc comment) — explicitly test the "admin uploads but forgets to Save" case: does the old attachment remain live on the public page, and does the admin UI make it obvious the upload isn't yet persisted? This is a realistic admin-UX trap.
- **Negative tests:** upload a non-PDF file (should be rejected by `FunnelFaqAttachmentUploadRequest`'s validation — check exact allowed mime types/size limit), upload an oversized file, upload with no file selected, upload while offline/network drops mid-upload.

### FUN-903 — FAQ content edits (question/answer text) via admin
- **Steps:** Edit an FAQ question's text so it no longer matches `/как се използва/i`.
- **Expected:** Re-verify FUN-209's fallback-to-index-0 behavior — this is the exact scenario that triggers it.

### FUN-904 — FAQ item order and count changes
- **Steps:** Add a new FAQ item, remove one, reorder them in admin.
- **Expected:** Public page and `faqJsonLd` both reflect the new order/count exactly; `activeFaqIndex` (a raw numeric index) doesn't end up pointing at the wrong item after a reorder if a user had one open and the admin changes content concurrently (unlikely but worth a quick sanity check — probably fine since each page load re-renders fresh).

### FUN-905 — Long FAQ answers / very short answers / empty answer
- **Steps:** Test boundary content lengths.
- **Expected:** No layout breakage; an empty answer string doesn't render a broken empty expanded panel.

---

## 10. Newsletter / Lead Capture (both inline `NewsletterSection` and `ExitIntentModal` share `LeadCaptureForm`)

### FUN-1001 — Happy path submission
- **Steps:** Enter a valid, previously-unseen email. Submit.
- **Expected:** `POST` to the funnel-lead endpoint, 201 response, form replaced with the success message (`isSubmitted` state), `trackLead()` fires exactly once. 

### FUN-1002 — Welcome email sent only on first capture
- **Steps:** Submit a brand-new email. Check mail (Mailtrap/log driver/etc.) and DB.
- **Expected:** `FunnelLeadWelcomeMail` (containing the usage-manual PDF) is sent. `funnel_leads` table gets exactly one new row for that email.
- **DB check:** `SELECT * FROM funnel_leads WHERE email = '...';` — one row, correct `created_at`.

### FUN-1003 — Re-submitting an already-captured email is silent and identical
- **Steps:** Submit the *same* email a second time (from either the newsletter form or the exit-intent modal, or both).
- **Expected:** Same 201 success response and success UI as a first-time submission (constant response, by design, to prevent email-enumeration probing) — **no** second welcome email sent, **no** duplicate row created (`firstOrCreate`).
- **DB check:** still exactly one row for that email; confirm no duplicate-row constraint violation surfaces as a 500 instead.
- **Security note:** this is an intentional anti-enumeration design — explicitly confirm the response is byte-for-byte/status-code identical for new vs. existing email, since a subtle timing or payload difference would defeat the purpose.

### FUN-1004 — Email normalization (case-insensitivity)
- **Steps:** Submit `Test@Example.com`, then later `test@example.com`.
- **Expected:** Both resolve to the same lead (`mb_strtolower` applied before `firstOrCreate`) — only one row, second submission treated as a resubmission (no second welcome email).

### FUN-1005 — Client-side validation
- **Steps:** Try submitting: empty field, malformed email (`notanemail`, `test@`, `test@.com`, `@example.com`, trailing/leading whitespace, an email with a valid-looking but absurd TLD), extremely long email (>255 chars).
- **Expected:** Browser's native `type="email" required"` blocks the obviously-empty/malformed cases before any request fires. For whatever slips through to the backend, `StoreFunnelLeadRequest`'s `required|string|email|max:255` rejects it with a 422 — confirm the frontend surfaces that server error message via `getErrorMessage` rather than a raw JSON blob.

### FUN-1006 — Rate limiting (`funnel-leads` limiter)
- **Steps:** Find the actual configured limit/window in `AppServiceProvider` and submit past it rapidly (script or repeated manual submits) from the same IP.
- **Expected:** Once the limit is exceeded, further requests return `429 Too Many Requests`; frontend shows a sensible error rather than an unhandled failure. Confirm the limit is generous enough not to block a legitimate visitor who mistypes their email a few times, but tight enough to actually deter abuse — this is a judgment call, document the actual configured numbers for the business to sign off on.

### FUN-1007 — Exit-intent modal trigger conditions
- **Steps (desktop only):** (a) Move the mouse to the very top edge of the viewport (toward the tab bar) *before* 10 seconds of dwell time — confirm it does NOT show. (b) Wait past 10s, then trigger the same exit gesture — confirm it DOES show. (c) After it's shown once, trigger the exit gesture again in the same session — confirm it does NOT show a second time. (d) Move the mouse between elements *within* the page (not exiting the viewport) — confirm this never triggers it (checks `relatedTarget !== null` guard). (e) Move the mouse out the *bottom* or *side* of the viewport — confirm only a top-edge exit triggers it (`event.clientY > 0` guard).
- **Expected:** All five behave exactly as designed; this is a conversion-sensitive feature so precision matters — an over-eager or buggy trigger actively hurts UX.

### FUN-1008 — Exit-intent session persistence
- **Steps:** Trigger the modal once, close it, reload the page (same tab/session), retrigger the exit gesture.
- **Expected:** Does NOT reappear in the same session (`sessionStorage` key `funnel.exit-intent-shown`). Open a *new* tab/session (or clear sessionStorage) and confirm it CAN show again.

### FUN-1009 — Exit-intent modal in private/incognito mode with storage restrictions
- **Steps:** Test in a browser mode where `sessionStorage` access might throw (rare, but the code explicitly guards for it).
- **Expected:** Per the code's own comment, if storage access throws, the modal simply never shows for that session (fails safe/quiet) rather than showing repeatedly or crashing.

### FUN-1010 — Exit-intent modal is desktop-only (mobile has no mouse-exit event)
- **Steps:** On an actual mobile device (not just a resized desktop browser window — touch input matters), attempt any interaction that might simulate the exit gesture.
- **Expected:** Modal never appears on touch devices (no `mouseout` events fire the same way); confirm this is true on real mobile Safari/Chrome, not just assumed.

### FUN-1011 — Exit-intent modal close mechanisms
- **Steps:** With the modal open, test: (a) clicking the `×` close button, (b) clicking the backdrop outside the modal dialog, (c) clicking *inside* the modal dialog (should NOT close — `stopPropagation`), (d) pressing `Escape`, (e) submitting the lead form successfully.
- **Expected:** (a)(b)(d) close it. (c) does nothing (event doesn't bubble to the backdrop handler). (e) — verify explicitly: does a successful submission auto-close the modal, or leave the success message showing inside the still-open modal until the user manually closes it? Either is defensible but should be a deliberate choice, not accidental.

### FUN-1012 — Exit-intent modal accessibility
- **Steps:** Open with keyboard-only navigation; use a screen reader.
- **Expected:** Focus moves into the modal on open (verify — not explicitly shown in the code read); `role="dialog"`, `aria-modal="true"`, `aria-labelledby` correctly pointing at the title are present (confirmed in code) — verify these actually produce correct screen-reader announcements. Tab should not escape the modal to background content while open (focus trap) — verify this is actually implemented somewhere (not obviously present in this component — flag as a possible accessibility gap if focus trapping is genuinely absent).

### FUN-1013 — Lead capture consent copy and link
- **Steps:** Check the consent line under the form links correctly to `/legal/privacy-policy` and that this route/document actually exists and is current (per the memory of a 2026-08 legal docs consolidation from 6→4 docs — confirm `privacy-policy` slug wasn't renamed/removed in that consolidation).
- **Expected:** Working link, accurate GDPR soft-opt-in language matching what the privacy policy itself says about marketing emails.

### FUN-1014 — Duplicate lead capture instances on one page don't double-fire tracking
- **Steps:** With the exit-intent modal open (containing its own `LeadCaptureForm`) while the newsletter section's separate `LeadCaptureForm` is also present lower on the page, submit via the modal.
- **Expected:** Only the modal's own submission fires `trackLead()` once — the newsletter section's independent form instance must not be affected/pre-filled/double-submitted.

---

## 11. Sticky Bars, Exit Modal Interplay, BOX NOW Badge (persistent chrome)

### FUN-1101 — Desktop sticky bar: one-way reveal
- **Steps:** Scroll down past the hero, then scroll back up above the hero, then back down again, repeatedly.
- **Expected:** Bar appears once the hero scrolls out of view and **stays visible for the rest of the page** including scrolled all the way to the footer — it must NOT hide again when scrolling back up into the hero re-triggers `isIntersecting` (re-verify: the code sets `showDesktopBar = !entry.isIntersecting`, which is a genuine two-way toggle in the code as read — confirm actual behavior: does scrolling back up to the hero hide the bar again? If so, this contradicts the doc comment's claim of "a one-way reveal... not a toggle" and is worth flagging as a discrepancy between comment and implementation to verify against real behavior in browser, not just the code.)
- **Regression risk:** This is exactly the kind of doc-comment-vs-implementation mismatch worth escalating if confirmed — verify empirically first.

### FUN-1102 — Desktop bar never overlaps footer content
- **Steps:** Scroll to the absolute bottom of the page (footer fully visible).
- **Expected:** The `body.has-funnel-buy-bar` reserved padding keeps the fixed bar from covering footer links/newsletter/legal links — visually confirm no overlap at multiple viewport widths (1366, 1440, 1920).

### FUN-1103 — Desktop bar content correctness
- **Steps:** Inspect the bar's product thumbnail, name, price, and rating.
- **Expected:** Thumbnail = product's primary image; price = the same computed `fromPriceLabel` used elsewhere; rating only shows if `reviewSummary` exists (see FUN-601); CTA scrolls to `#pricing`.

### FUN-1104 — Mobile sticky bar: three-zone visibility logic
- **Steps:** On an actual mobile viewport, scroll slowly through the entire page and note bar visibility at each zone boundary: hero → (hidden) → informational sections → (visible) → pricing-early cards → (hidden) → back into informational content between pricing-early and #pricing → (visible again, per zones) → #pricing cards → (hidden) → delivery/payment/returns → (visible) → FAQ onward → (hidden for good).
- **Expected:** Exactly matches the documented zone logic. Specifically verify the FAQ-onward hide is "directional" (`faqReached` based on `boundingClientRect.top <= 0`, not just intersection) — scroll back UP from below FAQ to above it and confirm the bar **reappears** (this is the explicit point of using directional logic instead of simple `isIntersecting`).

### FUN-1105 — Mobile bar CTA
- **Steps:** Tap the mobile bar's CTA while it's visible.
- **Expected:** Scrolls to `#pricing`; bar itself should then hide per the zone logic once `#pricing` scrolls into view (confirm no jarring flash where the bar is still visible directly over the section it just navigated to).

### FUN-1106 — Sticky bars don't appear/interfere before settings/product finish loading
- **Steps:** Throttle network heavily and observe the page during the loading phase.
- **Expected:** Neither bar mounts until `settingsLoading`/`isLoading` are both false (both effects explicitly gate on this) — no flash of a bar with missing/undefined price data.

### FUN-1107 — BOX NOW badge always-on positioning via portal
- **Steps:** Scroll the entire page; resize the window; check the badge's position never gets clipped or hidden behind other fixed elements (sticky bars, exit modal backdrop).
- **Expected:** Badge remains in its fixed corner position throughout, rendered via `createPortal(..., document.body)` so it escapes `.funnel-page`'s `overflow-x: hidden` clipping — confirm this actually works across Chrome, Firefox, Safari (the code comment specifically calls out a Chromium/WebKit clipping bug this portal works around — verify Firefox doesn't have some *different* unaddressed issue).

### FUN-1108 — BOX NOW badge admin toggle
- **Steps:** In admin settings, find and toggle `box_now_badge_enabled` off. Reload public page.
- **Expected:** Badge disappears (`publicSettings?.box_now_badge_enabled !== false` is the only gate — an explicit `false` hides it). Toggle back on, confirm it reappears.

### FUN-1109 — BOX NOW badge click target
- **Steps:** Click the badge.
- **Expected:** Scrolls to `#delivery-payment-returns` (see FUN-802) — confirm it does NOT navigate away from the page or open a new tab unexpectedly.

### FUN-1110 — BOX NOW badge accuracy vs. current promo state
- **Steps:** Near/after the 2026-09-30 free-shipping promo expiry (see section 14), re-check the badge's claim text ("БЕЗПЛАТНА ДОСТАВКА С BOX NOW" or similar curved text).
- **Expected:** If the promo has expired and BOX NOW is no longer free, this badge becomes a live false-advertising claim exactly like the historical incident already on record — this is the single highest-priority regression to watch across the 2026-09-30 boundary. Flag loudly if the badge text isn't tied to the same `isFreeShippingPromoActive()` state as the actual price.

### FUN-1111 — Layering/z-index sanity across all persistent chrome + exit modal simultaneously
- **Steps:** Trigger the exit-intent modal while both sticky bars are visible (scroll to a mobile-bar-visible zone first, on a build wide enough to show both mobile/desktop layouts isn't simultaneous, but test each viewport independently: mobile bar + modal, desktop bar + modal).
- **Expected:** Modal backdrop covers everything including sticky bars (proper z-index stacking); sticky bars aren't clickable through the modal backdrop; closing the modal restores normal interaction with the bars underneath.

---

## 12. Analytics & Tracking Events

### FUN-1201 — ViewContent fires once per real product view
- **Steps:** Load `/` fresh with devtools network tab (or pixel-helper extension) open. Reload via SPA navigation away and back.
- **Expected:** `trackFunnelViewContent(name, price.amount, price.currency)` fires exactly once per real page load of the product, keyed on product id — confirm it doesn't re-fire on every unrelated re-render (e.g. when `activeFaqIndex` state changes), and confirm it doesn't fire twice in React StrictMode dev builds if that's a concern for this codebase's dev tooling.

### FUN-1202 — AddToCart event payload accuracy
- **Steps:** Add each of the 4 packages individually (fresh cart each time) and inspect the fired event's `value`/`currency`.
- **Expected:** Matches the exact package price (not unit price, not cart total) for every package size, including the 1-pack and the fallback single-variant path (FUN-712).

### FUN-1203 — Lead event fires only on the funnel lead endpoint, once per successful submission
- **Steps:** Submit via newsletter section, then via exit-intent modal (both are legitimate, separate submissions if different emails).
- **Expected:** `trackLead()` fires once per successful submission, not on validation failures, not on a duplicate/resubmission attempt returning the same success (re-verify: does a resubmission of an already-known email still fire `trackLead()` client-side, since the client can't tell it was a resubmission? This is likely "yes, fires anyway" since the client only sees a 201 either way — confirm this is acceptable to the business, since it could slightly overcount unique leads in ad-platform reporting).

### FUN-1204 — Full funnel event chain end-to-end
- **Steps:** From a cold session: load `/` (ViewContent) → add a package (AddToCart) → go to `/cart` → proceed to `/checkout` (should fire InitiateCheckout/`trackBeginCheckout` per `CheckoutPage.tsx`) → complete payment (Purchase via `trackPurchase`).
- **Expected:** All four events fire in the correct order, exactly once each, with consistent product/value/currency data across the whole chain (the value reported at ViewContent/AddToCart should be traceable to the same amount that shows up in the final Purchase event's order total, accounting for shipping).
- **Verification method:** Use the ad platform's test-event tool (Meta Events Manager test events, GA4 DebugView) rather than just trusting the JS ran — confirm events actually *arrive* server-side, not just that the tracking function was called (network request could still fail silently, e.g. blocked by an ad-blocker in the tester's own browser — test with and without a content blocker to understand real-world data loss).

### FUN-1205 — Tracking with ad-blockers / privacy browsers
- **Steps:** Repeat the funnel with uBlock Origin / Brave / Safari ITP active.
- **Expected:** Page functionality (add to cart, checkout, payment) must work identically regardless of whether tracking pixels are blocked — confirm no code path treats a blocked/failed tracking call as blocking or delaying the actual user-facing action (tracking should never be awaited before proceeding).

---

## 13. End-to-End Purchase Journey (Funnel → Cart → Checkout → Payment → Confirmation)

*This is the actual conversion the whole funnel exists to produce — test it as seriously as the landing page itself.*

### FUN-1301 — Cart page after arriving from the funnel
- **Steps:** Add a package from the funnel, land on `/cart`.
- **Expected:** Correct item, quantity (1), unit price = package price, line total correct; "Proceed to checkout" CTA present and functional; item is fully editable (quantity change, remove) even though it originated from a "package" concept the cart itself may not understand as a bundle (confirm the cart just sees a normal variant + qty 1, with no funnel-specific metadata that could confuse cart-level logic like promotions).

### FUN-1302 — Adding a second, different package to the same cart
- **Steps:** From `/cart`, navigate back to `/` (funnel), add a *different* package.
- **Expected:** Cart now has two separate line items (different variant ids) with correct independent quantities/totals — confirm navigating back to `/` doesn't reset/lose the existing cart (guest cart persistence — cookie/session based, presumably).

### FUN-1303 — Guest checkout full happy path (Card payment)
- **Steps:** As a guest, complete all 4 checkout steps: Customer Info → Delivery (BOX NOW, default, free shipping active) → Review (accept all legal docs) → Payment (Card) → complete the iCard modal successfully.
- **Expected:** Order created; redirected to `/order-confirmation/:orderId` with the guest access token correctly passed via route state; order status ends at `Paid`; confirmation page shows correct items/total/shipping (€0 for BOX NOW under the active promo)/payment method.
- **DB check:** `orders` row: correct `grand_total`, `shipping_price = 0.00`, `shipping_carrier = 'box_now'`. `payments` row: `status = 'paid'`, `payment_method = 'card'`, `provider = 'icard'`. `payment_transactions` has `initiated` + `webhook` (or `return`) rows in order.

### FUN-1304 — Customer Info step validation
- **Steps:** Try submitting with each field invalid in turn: empty first/last name, malformed email (see FUN-1005-style cases), empty phone, a landline-shaped or non-`+3598…` phone number, a phone with too few/too many digits after the prefix.
- **Expected:** Each produces the exact matching error key/message (`checkoutCopy.errors.*`); the step cannot advance (`handleNext` blocks on `validateCustomerStep`) until all pass.

### FUN-1305 — Phone field normalization
- **Steps:** Using the dedicated phone input, try entering a number with a leading `0` (local format), and confirm the underlying value gets reassembled to `+3598XXXXXXXX` before validation/submission (per `PhoneField.tsx`'s prefix-stripping logic).
- **Expected:** All common human-entered formats normalize correctly; only genuinely invalid numbers (wrong length, doesn't start with 8 after the country code, non-numeric characters) are rejected.

### FUN-1306 — Delivery step: BOX NOW default pre-selection, exactly once
- **Steps:** Load checkout fresh — confirm BOX NOW is pre-selected once shipping methods load. Manually switch to Speedy. Go back a step and forward again (Customer Info → Delivery → back → forward).
- **Expected:** BOX NOW auto-selects only on first load; after manually switching to Speedy, going back/forward through steps must NOT silently reset the selection back to BOX NOW (the `defaultMethodApplied` ref guard exists specifically to prevent this — confirm it actually works across step navigation, not just re-renders).

### FUN-1307 — BOX NOW free shipping promo reflected correctly at checkout
- **Steps:** With today's date before 2026-09-30, select BOX NOW.
- **Expected:** Shipping price shown as €0.00 / "Безплатно"; order total excludes any shipping charge; this must match the badge/trust-item claims made back on the funnel landing page (cross-reference FUN-801/FUN-1110).

### FUN-1308 — BOX NOW office/locker selection (delivery type = Locker)
- **Steps:** With BOX NOW selected, choose a locker from the prefetched list; try leaving no locker selected and attempting to proceed.
- **Expected:** Locker selection is required (`requires_office` / `stepErrors.shipping_office_id`) before advancing; the prefetched office list (loaded on mount, in parallel across all carriers) is already populated with no spinner delay when reaching this step; search/filter within the office list (if present) works correctly, including Cyrillic city names.

### FUN-1309 — Speedy shipping selection (address delivery)
- **Steps:** Switch to Speedy, address-delivery type. Fill in a real settlement, postal code (auto-derived from settlement — confirm this), street address line, apartment (optional).
- **Expected:** Correct non-zero Speedy flat rate (per memory, €5.99 or current configured rate) shown; settlement dropdown/autocomplete works with the prefetched ~1MB settlement list including Cyrillic search; postal code auto-populates and is NOT independently editable if it's meant to be derived (confirm actual behavior — editable vs. derived-and-locked).

### FUN-1310 — Speedy office/locker alternative delivery types
- **Steps:** If Speedy offers office and/or locker/machine pickup in addition to home delivery (per `ShippingService::label()`'s "(автомат)" suffix for Speedy locker), test each type.
- **Expected:** Correct label suffixes ("(до адрес)" / "(автомат)"), correct office list per type, correct required-office validation.

### FUN-1311 — Switching shipping carrier/method mid-flow clears the previously selected office
- **Steps:** Select BOX NOW + a specific locker, then switch to Speedy.
- **Expected:** `handleSelectMethod` explicitly resets `selectedOffice` to `null` on any method change — confirm the previously chosen BOX NOW locker doesn't leak into a Speedy order, and the UI doesn't show a stale "selected" locker highlighted after switching.

### FUN-1312 — Invoice (VAT) opt-in flow and its interaction with billing address
- **Steps:** (a) Toggle "I want an invoice" ON with address-delivery selected and "billing same as shipping" checked — confirm no separate billing address fields are required/shown. (b) Toggle invoice ON with an office/locker delivery type selected — confirm `billingSameAsShipping` is force-set to `false` (per the explicit effect for this exact case, since there's no shipping address to copy from) and that separate billing fields become required. (c) Toggle invoice ON, uncheck "same as shipping" manually while using address delivery — separate billing fields required. (d) Try leaving company/VAT number blank while invoice is ON — must block advancement.
- **Expected:** Exactly matches the described conditional logic; test the specific ordering edge case called out in the code comment — toggling invoice ON *before* vs. *after* selecting an office-type delivery method — both orders must land in the same correct state (the effect is explicitly designed to stay correct "regardless of whether the invoice opt-in or the method is picked first").

### FUN-1313 — VAT number format validation
- **Steps:** Enter an obviously invalid VAT number format, a valid Bulgarian VAT format, blank.
- **Expected:** Confirm whatever validation actually exists (the code as read only checks `!customer.vat_number.trim()` — i.e., presence, not format). If there's genuinely no format validation, note this as a potential data-quality gap for invoicing, not a functional bug per se — flag for product decision.

### FUN-1314 — Review step: legal document acceptance gating
- **Steps:** Reach the Review step with the current (post-consolidation, 4-document) legal document set. Try clicking "Next"/placing the order without checking all boxes; check some but not all; check all.
- **Expected:** Cannot proceed/place order unless `legalDocuments.every(doc => accepted)` — partial acceptance blocks with the correct error; all 4 documents (not the old 6, not fewer) are listed and linkable/viewable in full before acceptance (skimming past to check the box shouldn't be the only way to see them — confirm documents open in a readable view, e.g. modal or new tab, not just a bare checkbox with a label).

### FUN-1315 — Order Review step displays fully accurate summary
- **Steps:** Cross-check every value shown in `OrderReviewStep` (items, customer, address, billing, shipping method + price, legal doc list) against what was actually entered/selected in prior steps.
- **Expected:** Perfect fidelity — no stale values from a prior edit, no mismatched shipping price if the customer went back and changed carriers after first reaching Review.

### FUN-1316 — Payment step: only Card is offered
- **Steps:** Reach the Payment step.
- **Expected:** No cash-on-delivery option is rendered under any condition (BOX NOW or otherwise) — this directly re-verifies the code's current state against the (now-outdated) prior expectation that COD was available for BOX NOW. If `PaymentStep.tsx` has any leftover conditional UI referencing BOX NOW + COD from the earlier implementation, it must render nothing/be dead code, not a broken half-visible option.
- **Regression risk:** High — this is a recent revert (COD added, then removed again); leftover UI branches are exactly the kind of thing that survives a quick revert.

### FUN-1317 — Stored payment methods (returning logged-in customer)
- **Steps:** As a logged-in customer with at least one previously stored card (`StoredPaymentMethod`), reach Payment step.
- **Expected:** Stored card(s) offered as a selectable option alongside "pay with a new card"; selecting a stored method and placing the order calls `createStoredCardSession` correctly; a guest (no `user_id`) never sees stored-card UI, and attempting to force a `stored_payment_method_id` as a guest is rejected server-side (`PaymentService::initiate` throws `RuntimeException` if `order->user_id === null`) — try to reproduce this via direct API manipulation (devtools/Postman) as a security-adjacent check, not just trusting the UI hides it.

### FUN-1318 — iCard modal — success path
- **Steps:** Place the order, complete the iCard modal with valid sandbox/test card details.
- **Expected:** `handlePaymentSuccess` fires, `recordPaymentReturn` is called (best-effort — confirm a failure here doesn't block navigation, per its explicit try/catch), redirected to confirmation page with correct order id and guest token in route state.

### FUN-1319 — iCard modal — declined/error card
- **Steps:** Use a test card configured to be declined.
- **Expected:** `handlePaymentError` sets `paymentOutcome = 'error'`; the danger alert (`checkoutCopy.paymentStep.modal.paymentError`) shows; a "Retry" button appears and, when clicked, calls `handleRetryPayment` → `initiatePayment` again, which per `PaymentService::initiate`'s own doc comment always mints a **fresh** attempt/transaction reference rather than reusing the failed one (confirm this actually happens — check `payments` table for a new row, not a reused `transaction_reference`, since iCard itself rejects duplicate OrderIDs).
- **DB check:** two distinct `payments` rows for the same order after one failure + one retry, the first left in a non-final `Failed`/`Initiated` state as harmless audit clutter, per the code's own description.

### FUN-1320 — iCard modal — customer-cancelled
- **Steps:** Open the iCard modal, then explicitly cancel/close it from within the gateway UI (not just closing the browser tab).
- **Expected:** `handlePaymentCancel` → `paymentOutcome = 'cancelled'` with the warning-styled alert; Retry available; order itself is NOT left in a broken pending-forever state — confirm what status the order/payment actually end up in (does `PaymentService::cancel` ever get invoked from this path, or does it stay `AwaitingPayment` indefinitely until abandoned? Trace this carefully — an order stuck forever in `AwaitingPayment` with no automatic timeout/cleanup is a real data-hygiene issue worth flagging even if not strictly a "bug" in this flow).

### FUN-1321 — Payment session unavailable (gateway misconfigured)
- **Steps:** In a test environment, misconfigure iCard credentials, then place an order.
- **Expected:** Order is still created (per the defensive UI branch for `!activePayment.payment.modal_session`), but the customer sees the "unavailable" alert with a Retry option rather than a blank panel — confirm this genuinely recovers once configuration is fixed and Retry is clicked, rather than requiring the customer to abandon and re-place the whole order.

### FUN-1322 — Duplicate order submission protection
- **Steps:** On the Payment step (before `activePayment` is set), rapidly double-click "Плати с карта" / attempt to submit twice quickly, and separately, refresh the browser mid-submission.
- **Expected:** `isSubmitting` disables the button after the first click (confirm truly disabled, not just visually); a page refresh mid-`handlePlaceOrder` should not result in two orders for one checkout attempt — check whether any client-side idempotency key is sent, or whether this relies purely on the disabled-button UX (which a refresh trivially defeats) — if a refresh really can double-submit, this is a genuine duplicate-order risk worth flagging as high priority per the skill's "Duplicate Action Testing" guidance (§28).

### FUN-1323 — Back button / browser navigation during checkout
- **Steps:** Use the browser Back button at each step (Customer Info, Delivery, Review, Payment, and again once `activePayment` is set / iCard modal is open).
- **Expected:** Document actual behavior at each point — does Back navigate within the SPA's own step state (since steps aren't separate URLs — confirm this: `step` is local React state, not reflected in the URL) or does it leave `/checkout` entirely and lose all entered data? If steps aren't in the URL, Back from step 2 likely exits checkout entirely rather than returning to step 1 — this is worth explicitly confirming as either acceptable (with an "are you sure" of some kind) or a real conversion-killing gap, since accidental Back-button taps are common on mobile.

### FUN-1324 — Refresh mid-checkout at each step
- **Steps:** Hard-refresh the browser at each of the 4 steps, and once with `activePayment` set (iCard modal open).
- **Expected:** Since step state is local (not URL-driven), a refresh very likely resets to step 0 with all entered data lost (cart itself should persist since that's server/cookie-backed, but customer info, delivery selection, invoice details, and legal-acceptance checkboxes are almost certainly wiped). Confirm this precisely, then judge whether it's an acceptable UX trade-off or worth flagging as a conversion risk — losing a fully-filled 4-step form to an accidental refresh is a classic drop-off cause. If `activePayment` existed pre-refresh, does the customer end up in a broken state with an already-created order they can no longer see/complete payment for from this page? Check whether the order is still recoverable (e.g. does `/order-confirmation/:orderId` work if they know/can find the id, even without the guest token in route state)?

### FUN-1325 — Two browser tabs, same guest cart, concurrent checkout
- **Steps:** Open `/checkout` in two tabs from the same guest session/cart. Complete the order fully in Tab A. Then attempt to complete it in Tab B.
- **Expected:** Tab B's cart should reflect that the cart is now empty (or its checkout attempt should fail gracefully — e.g. cart items no longer present) rather than creating a second duplicate order from the same original cart contents.

### FUN-1326 — Empty cart reaching `/checkout` directly
- **Steps:** With an empty cart, navigate directly to `/checkout` (typed URL or stale bookmark).
- **Expected:** `EmptyState` renders (per the explicit `cart.items.length === 0 && !activePayment` branch) with a sensible message and a way back to shopping — not a broken checkout form with nothing to check out.

### FUN-1327 — Legal documents fail to load
- **Steps:** Force the legal documents endpoint to error.
- **Expected:** `legalDocumentsError` shown; the Review step cannot be validated/passed (`validateReviewStep` explicitly returns `false` if `!legalDocuments`) — confirm the customer isn't stuck with no explanation and no retry path.

### FUN-1328 — Settlement list load failure
- **Steps:** Force `fetchSettlements` to fail.
- **Expected:** `settlementsError` shown at the Delivery step for address-based delivery; confirm the customer can still select an office/locker-based carrier (BOX NOW/Speedy pickup) as an alternative that doesn't need the settlement list, rather than being fully blocked from checking out at all.

### FUN-1329 — Order confirmation page correctness
- **Steps:** Land on `/order-confirmation/:orderId` after a successful payment, both as a fresh-session guest (relying on route-state token) and via a direct reload of that URL.
- **Expected:** On direct reload (no route state, guest token lost from memory), confirm how the page authenticates the guest to view their own order — does it rely on a persisted token elsewhere (cookie/localStorage) or does a reload genuinely lock the guest out of viewing their own just-placed order? This is a real, common scenario (customer accidentally refreshes the confirmation page) worth nailing down precisely.

### FUN-1330 — Order confirmation email
- **Steps:** After a successful order, check the customer's inbox.
- **Expected:** Confirmation email arrives with correct order number, items, total, shipping method, and estimated delivery; Bulgarian formatting throughout (date format `DD.MM.YYYY`, currency EUR symbol).

---

## 14. Business-Rule & Legal/Compliance Boundary Testing

### FUN-1401 — BOX NOW free-shipping promo expiry boundary (2026-09-30 23:59:59)
- **Steps:** In a test environment, override `BOX_NOW_FREE_SHIPPING_PROMO_UNTIL` (env var) to values just before, exactly at, and just after a test boundary timestamp (e.g. `now + 2 minutes`), and observe real-time behavior crossing it — don't rely only on static before/after checks.
- **Expected:** Price is exactly €0.00 while `now() <= threshold`, and reverts to the real flat BOX NOW rate the instant it's exceeded — confirm the comparison is inclusive of the exact boundary second as coded (`<=`), and that this reflects consistently in: the shipping method list (`ShippingService::availableMethods()`), the checkout summary, the final order total, AND the funnel landing page's floating badge + trust-item copy (cross-reference FUN-1110/FUN-801) — **all four must flip together**, not just the backend price.
- **Priority:** P0 as the date approaches (2026-09-30 is one month from today) — schedule an explicit retest of this exact scenario in the days surrounding that date in production, not just in this pre-emptive test pass.

### FUN-1402 — No accidental €25-threshold logic resurfacing
- **Steps:** Test order totals both below and above an arbitrary €25 mark with BOX NOW selected.
- **Expected:** Shipping price behaves identically regardless of order total (flat free-during-promo / flat-rate-after, per current code) — confirm no dormant/half-implemented threshold logic exists anywhere that could make shipping price incorrectly total-dependent (the memory notes an earlier approved-but-never-built €25-threshold plan — confirm it truly never landed).

### FUN-1403 — Dual EUR/BGN price display legal window
- **Steps:** Confirm `DUAL_PRICE_BGN` is `false` and that no price anywhere on the funnel page (hero, packages, sticky bars, checkout summary) shows a BGN-denominated figure.
- **Expected:** Consistent EUR-only display everywhere, matching the code's current (correct, for today's date) legal posture. Flag to the business that this is a hardcoded constant with a comment referencing a fixed historical date range — if BGN dual-display ever needs to return (e.g. a future regulation), someone has to remember to flip this manually; it won't self-trigger.

### FUN-1404 — Card-only payment claim consistency, end to end
- **Steps:** Trace every place payment method is mentioned or implied: funnel trust items, `DeliveryPaymentReturnsSection` copy, checkout's Payment step, order confirmation, any transactional email, admin order detail view.
- **Expected:** 100% consistent "card only" story everywhere — zero surviving references to cash-on-delivery as a live option (leftover copy strings, disabled-but-visible UI, stale FAQ answers mentioning "Наложен платеж" as available are all real risks given how recently this was reverted).

### FUN-1405 — Funnel packages must total exactly 4 — admin-side enforcement
- **Steps:** In `/admin/funnel`, attempt to save with 3 packages, or 5 packages (if the UI even allows constructing such a payload — try via direct API call if the UI itself prevents it, to test server-side enforcement independent of client UI).
- **Expected:** `FunnelPackagesRequest`'s `size:4` rule rejects anything other than exactly 4 with a 422 — confirm the admin UI's own error messaging is clear about *why* (not just a generic "validation failed").

### FUN-1406 — Package variant must belong to the configured product
- **Steps:** Via direct API call (bypassing whatever the admin UI's own dropdown restricts you to), attempt to save a package payload where one `variant_id` belongs to a *different* product than the one specified.
- **Expected:** Rejected with the specific error `"The selected variant does not belong to the chosen product."` on that exact package index — confirm the error correctly identifies *which* of the 4 packages is invalid, not a generic top-level error.

---

## 15. Admin: Funnel Configuration Surface (`/admin/funnel`)

### FUN-1501 — Authorization — non-admin cannot access
- **Steps:** Attempt to hit `GET /api/v1/admin/funnel` (and every other admin funnel endpoint) as: an unauthenticated guest, a logged-in regular customer, an admin without the specific policy permission (if role granularity exists).
- **Expected:** `FunnelConfigPolicy`/`FunnelLeadPolicy` correctly deny with 401/403 as appropriate for each case — test this via direct API calls, not just by confirming the admin UI hides the nav link (hiding a link is not access control).

### FUN-1502 — Admin action logging completeness
- **Steps:** Perform every admin funnel action once: toggle on, toggle off, update packages, update each of the 12 content sections, upload an FAQ attachment.
- **Expected:** Each produces exactly one correctly-labeled `admin_action_logs` entry (`funnel.toggled`, `funnel.packages.updated`, `funnel.content.{section}.updated`, `funnel.faq_attachment.uploaded`) with the correct admin user id and a sensible `changes` payload for audit purposes.
- **DB check:** `SELECT * FROM admin_action_logs WHERE action LIKE 'funnel.%' ORDER BY created_at DESC;`

### FUN-1503 — Admin payload shows raw/stale config, unlike public payload
- **Steps:** Deliberately create a stale product/variant reference (see FUN-711/FUN-202). Load `/admin/funnel`.
- **Expected:** Per `adminPayload()`'s explicit design, the admin sees the *raw* stored `product_id`/`packages` (so they can find and fix the problem), not a silently-empty view like the public page gets. Confirm the admin UI actually renders something actionable here (e.g. a warning, or the broken variant picker visibly showing "unknown/deleted variant") rather than also just looking empty/fine.

### FUN-1504 — Content editor per-section save isolation
- **Steps:** Edit and save `funnel.hero` only. Confirm no other section (`intro`, `why`, etc.) was touched/reset.
- **Expected:** `updateSection()` only writes the one `content_blocks` row keyed `funnel.hero` — verify via DB that sibling rows' `updated_at` timestamps are unchanged.

### FUN-1505 — Content editor rejects an unknown section key
- **Steps:** Via direct API call, `PUT`/`PATCH` to the content-update endpoint with a section name not in `FunnelContentService::FUNNEL_SECTIONS` (e.g. a typo, or a since-removed section like the old "natural_eco"/"positioning" if the UI still exposes them but they're unused on the public page).
- **Expected:** `InvalidArgumentException` surfaces as a proper 4xx/5xx error, not silently written to a garbage key. Also specifically check: `natural_eco`, `features`, `positioning`, `history` are all still listed in `FUNNEL_SECTIONS` per the code, but their corresponding public-page sections were explicitly "removed" per `FunnelLandingPage.tsx`'s own doc comment map — confirm the admin content editor still lets an admin edit these orphaned sections (wasted admin effort/confusion) and that doing so has zero visible effect on the live page, which itself is worth flagging as a minor cleanup opportunity.

### FUN-1506 — Concurrent admin edits (two admins editing funnel content simultaneously)
- **Steps:** Two admin sessions open `/admin/funnel`; both edit the same section (`hero`) with different values; both save, B slightly after A.
- **Expected:** Last-write-wins per section (`updateOrCreate` — no optimistic locking evident in the code) — confirm this is acceptable to the business (likely fine for a low-concurrency single-admin-team tool, but document the behavior rather than assuming).

### FUN-1507 — Toggling funnel mode while an in-progress checkout session is active
- **Steps:** A customer is mid-checkout (funnel mode ON when they started). While they're on `/checkout` (step 2, say), an admin toggles funnel mode OFF.
- **Expected:** Since the toggle only affects `/`, confirm the in-progress `/checkout` session is completely unaffected and the customer can complete their purchase normally regardless of what happens to funnel mode mid-session.

---

## 16. Admin: Leads Management (`/admin/leads`)

### FUN-1601 — Leads list, pagination, ordering
- **Steps:** Seed 30+ leads. View the leads list.
- **Expected:** 25 per page (per `paginate(25)`), newest-first by `id` (not `created_at`, deliberately, per the code comment, for deterministic same-second ordering) — confirm two leads created in the same second still order correctly and consistently across page loads.

### FUN-1602 — Leads search by email (substring match)
- **Steps:** Search for a partial email fragment (e.g. a domain like `gmail.com`, or a partial local-part).
- **Expected:** Returns all leads whose email contains the substring (`LIKE '%...%'`), case sensitivity behavior should be checked against the DB collation in use; confirm a search with SQL wildcard characters typed literally (`%`, `_`) doesn't behave unexpectedly (e.g. searching literally for `%` shouldn't return everyone unless that's accepted/intended behavior — verify no raw unescaped user input reaches the query in a way that could be abused, even though this is a `LIKE` bound parameter and should be safe from injection — confirm it actually is parameterized, not string-concatenated).

### FUN-1603 — CSV export completeness and correctness
- **Steps:** Export leads to CSV with a large lead count (test the `chunk(500, ...)` path with >500 rows if feasible).
- **Expected:** CSV contains a header row (`email,created_at`), every lead present exactly once (no duplicates/omissions across chunk boundaries), `created_at` in ISO8601, correct `Content-Type: text/csv`, filename `funnel-leads.csv`. Emails containing commas or quotes (unlikely for a valid email, but verify `fputcsv`'s built-in escaping handles it) don't corrupt the CSV structure.
- **DB check:** row count in the export exactly matches `SELECT COUNT(*) FROM funnel_leads;`.

### FUN-1604 — Lead deletion (GDPR erasure)
- **Steps:** Delete a specific lead from the admin UI.
- **Expected:** Row genuinely removed from `funnel_leads` (hard delete, not soft — confirm which it is, since GDPR erasure requests generally expect real removal); an `admin_action_logs` entry (`funnel.leads.deleted`) records which email was removed and by whom, for audit purposes, even though the underlying record is gone.
- **DB check:** `SELECT * FROM funnel_leads WHERE id = <deleted-id>;` returns nothing.

### FUN-1605 — Re-subscribing after deletion
- **Steps:** Delete a lead's email via admin, then have that same email resubmit via the public funnel form.
- **Expected:** Treated as a genuinely new lead (`firstOrCreate` finds no existing row) — a fresh welcome email is sent again, since from the system's perspective this is a first-time signup post-erasure. Confirm this matches the intended GDPR posture (erasure means "we forgot you," so resubscribing legitimately restarts the relationship) rather than some hidden previously-existing-email tombstone blocking a legitimate resubscription.

---

## 17. Cross-Cutting: Responsive Testing

Test the entire funnel page (all sections, both sticky bars, exit modal, badge) at minimum at these viewports, per the skill's standard set: **320×568, 360×800, 375×812, 390×844, 412×915, 768×1024, 1024×768, 1366×768, 1440×900, 1920×1080.**

### FUN-1701 — No horizontal scroll at any viewport
- **Expected:** `.funnel-page`'s `overflow-x: hidden` should prevent this structurally, but verify no individual element (long unbroken text, an oversized image, a wide table if any exists) forces overflow that the container clips awkwardly rather than the content simply wrapping/scaling correctly.

### FUN-1702 — Package cards stack/reflow correctly across breakpoints
- **Expected:** 4-card grid degrades sensibly from desktop multi-column to mobile single-column; the "featured" 5-pack card's visual emphasis (larger size/border/badge) doesn't break the grid at intermediate tablet widths (768–1024 is the classic breakage zone for featured-card layouts).

### FUN-1703 — Sticky bars don't overlap critical content or each other at any width
- **Expected:** Mobile bar's fixed height doesn't cover the bottom of the FAQ accordion, the newsletter form's submit button, or footer links, at every mobile viewport width tested — re-verify specifically at 320×568 (smallest/tightest) where cramped fixed UI most commonly breaks.

### FUN-1704 — BOX NOW badge doesn't overlap the mobile sticky bar
- **Expected:** Both are `position: fixed`; confirm their corner placements don't collide/overlap at small viewports where screen real estate is tight.

### FUN-1705 — Exit-intent modal sizing on narrow-but-still-desktop widths
- **Expected:** Since exit-intent is desktop-only by trigger mechanism, verify it still renders sensibly on a narrower desktop/laptop window (e.g. a resized 1024-wide browser, still using a mouse) rather than assuming desktop always means "wide."

### FUN-1706 — Touch target sizing on mobile
- **Expected:** Package CTA buttons, FAQ accordion headers, sticky bar CTA, exit modal close button all meet a reasonable minimum touch target size (~44×44px) and have adequate spacing from neighboring tappable elements to avoid mis-taps.

---

## 18. Cross-Cutting: Browser Compatibility

Test at minimum: **Chrome, Firefox, Safari (desktop + iOS), Edge.**

### FUN-1801 — IntersectionObserver-driven features (scroll-reveal, both sticky bars) across browsers
- **Expected:** Consistent behavior everywhere; Safari has historically had quirks with `IntersectionObserver` + `position: fixed` interplay — specifically re-test FUN-1101/FUN-1104 on Safari.

### FUN-1802 — BOX NOW badge portal rendering (the explicitly-called-out Chromium/WebKit clipping issue)
- **Expected:** Confirm the portal fix actually resolves the issue on real Chrome AND real Safari (not just one), and separately confirm Firefox — which the code comment doesn't explicitly mention testing against — doesn't have some other overflow-clipping quirk of its own.

### FUN-1803 — `sessionStorage` exit-intent gating on Safari's stricter privacy defaults
- **Expected:** Re-test FUN-1008/FUN-1009 specifically on Safari (especially iOS Safari and Safari Private Browsing), which has historically had the strictest storage-partitioning/access behavior of the major browsers.

### FUN-1804 — CSS `:has()`, `backdrop-filter`, custom properties, or other modern CSS used in `funnel.css`
- **Expected:** Spot-check `funnel.css` for any bleeding-edge CSS features and confirm graceful degradation (not a broken/invisible layout) on the oldest browser versions still in the site's actual analytics traffic.

### FUN-1805 — iCard modal/redirect flow across browsers
- **Expected:** The hosted payment modal (iCard IPG) is a third-party embed — test the full success/decline/cancel cycle on Safari specifically, since third-party iframe/popup/redirect flows are the most common cross-browser breakage point in checkout, and Safari's ITP/cross-site cookie restrictions can specifically break payment redirect flows that rely on cookies across origins.

---

## 19. Cross-Cutting: Accessibility

### FUN-1901 — Keyboard-only navigation through the entire funnel + checkout
- **Steps:** Unplug the mouse. Tab through the page from top to bottom, then through the full checkout flow, using only Tab/Shift+Tab/Enter/Space/Arrow keys.
- **Expected:** Logical tab order (top-to-bottom, matching visual order — package cards, FAQ accordion, form fields, checkout steps); visible focus indicator at every stop; the exit-intent modal traps focus while open (re-verify FUN-1012) and Escape closes it; every interactive element (package Add buttons, FAQ headers, accordion, sticky bar CTAs, shipping method radio/cards, legal-document checkboxes) is reachable and activatable without a mouse.

### FUN-1902 — Screen reader pass on high-value flows
- **Steps:** Using a real screen reader (NVDA/JAWS on Windows, VoiceOver on macOS/iOS), navigate the pricing section, FAQ accordion, and full checkout.
- **Expected:** Package cards announce price, savings, and stock status meaningfully (not just visual badges with no accessible text equivalent); FAQ accordion announces expanded/collapsed state; form fields have properly associated labels and error messages are announced when they appear (not just visually rendered).

### FUN-1903 — Image alt text
- **Steps:** Audit every `<img>` on the funnel page (product photos, package images, sticky-bar thumbnail).
- **Expected:** Meaningful alt text where the image conveys information (package images do have descriptive alt text per the code — `Пакет от ${pack_size} броя Miswak`); purely decorative images (sticky-bar thumbnail has `alt=""` per the code) correctly marked as decorative so screen readers skip them rather than announcing something unhelpful.

### FUN-1904 — Color contrast
- **Steps:** Run an automated contrast checker (axe, Lighthouse) against text on top of the featured package card's background, the sticky bars, the BOX NOW badge's curved text, and any colored badge/pill elements (savings badge, low-stock warning).
- **Expected:** WCAG AA minimum (4.5:1 for normal text, 3:1 for large text) everywhere; the low-stock and out-of-stock text especially, since these are often styled in low-contrast muted colors that can fail contrast while looking "fine" to a sighted tester without measurement.

---

## 20. Cross-Cutting: Network-Aware & Performance-Symptom Testing

### FUN-2001 — Slow 3G simulation across the full funnel-to-purchase journey
- **Steps:** Throttle to "Slow 3G" in devtools; complete the entire journey from landing on `/` through order confirmation.
- **Expected:** Loading states appear promptly and meaningfully at every async boundary (product fetch, reviews, settings, shipping methods, settlements, offices, order placement, iCard modal load); no indefinitely-spinning UI; no duplicate requests fired due to a slow response being mistaken for a failure and retried by an impatient double-click (re-cross-reference FUN-709/FUN-1322 specifically under throttled conditions, since slow networks are exactly when race conditions between disabled-state and click-handler become visible).

### FUN-2002 — Offline mid-flow
- **Steps:** Go offline (devtools network → Offline) at various points: mid-scroll on the landing page, right before submitting the lead form, right before placing the order.
- **Expected:** Lead form and place-order both surface a clear, honest network-error message (not a generic crash or infinite spinner) and allow retry once back online, without having silently "succeeded" from the UI's perspective while the request actually failed.

### FUN-2003 — Large settlement list (~1MB) load impact
- **Steps:** Measure actual load time/impact of the settlements prefetch on a throttled connection, on both desktop and a real mid-range mobile device.
- **Expected:** Confirm this genuinely prefetches in the background without blocking the customer from proceeding through earlier checkout steps that don't need it yet (Customer Info step shouldn't wait on this).

### FUN-2004 — Image weight and format on the funnel page
- **Steps:** Audit image sizes for the `/funnel/v2/*.webp` photography and package images.
- **Expected:** Reasonably optimized (WebP already in use — good); `loading="lazy"` present on below-the-fold images (package images do have this per the code — confirm hero images correctly do NOT lazy-load, since lazy-loading an above-the-fold hero image actively hurts LCP).

---

## 21. Cross-Cutting: Data Integrity & Direct DB Verification Checklist

Use these as spot-checks after any significant test session, given you have DB access:

- **`funnel_configs`**: exactly one row, ever. `is_enabled` matches the admin UI's last-set state exactly. `product_id` and `packages` (JSON) match what `/admin/funnel` currently shows.
- **`funnel_leads`**: `email` column has no duplicate rows differing only by case (confirms `mb_strtolower` is applied consistently, not just on write but that no legacy mixed-case rows exist from before this normalization, if this feature has been live a while — a worthwhile historical-data audit).
- **`content_blocks`**: exactly one row per `funnel.*` key actually in use; no orphaned rows with a `funnel.` prefix that doesn't match any current `FUNNEL_SECTIONS` entry (would indicate a renamed/removed section leaving dead data behind).
- **`orders`/`payments`/`payment_transactions`**: for every test order placed during this pass, verify `orders.grand_total = orders.subtotal + orders.shipping_price - orders.discount_total` (or whatever the actual formula is — confirm arithmetic, don't just eyeball it) and that it matches what the confirmation page/email displayed. Verify `payments.amount` equals `orders.grand_total` exactly (the webhook handler explicitly rejects amount mismatches — confirm this rejection path is never silently hit in legitimate flows by checking `payment_webhook_logs` for any `_rejected_reason: amount_mismatch` entries after a normal test run, which would indicate a real total-calculation bug).
- **`payment_webhook_logs`**: every payment attempt has a corresponding logged webhook delivery (or an explicit reconciliation via status-check) — no payment silently stuck `Initiated` forever with zero webhook trace, which would indicate a lost/undelivered webhook worth escalating to infra.
- **`admin_action_logs`**: complete audit trail for every admin funnel action performed during this test pass (cross-reference against FUN-1502).
- **`shipments`**: only created when the (currently manual, per the code's own comment — "not called automatically anywhere in this sprint") admin action actually triggers `ShippingService::createShipment()` — confirm test orders don't unexpectedly already have shipment rows if no such action was taken, and conversely that a real fulfillment action does produce one correctly.

---

## 22. Regression Checklist — Re-run After Any Funnel-Related Deploy

1. Funnel mode toggle still defaults/behaves correctly (FUN-101–103).
2. All 4 packages still resolve and price correctly, savings badges still compute correctly (FUN-701–704).
3. BOX NOW is still free (until 2026-09-30) and this is still true consistently across landing page badge, checkout price, and order total (FUN-1401).
4. Card is still the only payment method everywhere, with zero leftover COD UI (FUN-1316, FUN-1404) — **specifically re-check this one after every deploy until confidence is high it won't silently regress again**, given its recent revert history.
5. Lead capture still works, doesn't double-email on resubmission, still respects the rate limiter (FUN-1001–1006).
6. Exit-intent modal still triggers/gates correctly, doesn't regress into showing on mobile or showing twice per session (FUN-1007–1009).
7. Both sticky bars still show/hide at the correct scroll zones (FUN-1101, FUN-1104).
8. Full checkout journey from a funnel add-to-cart through to a paid order confirmation still completes without error, on both BOX NOW and Speedy (FUN-1303, FUN-1309).
9. Admin funnel config (toggle, packages, content, FAQ attachment, leads) still enforces the same validation and authorization rules (Section 15–16).
10. No new console errors/network failures introduced anywhere in the funnel page or checkout flow (baseline devtools sweep).
