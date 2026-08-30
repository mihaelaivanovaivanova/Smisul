# Funnel Mode QA — Severity-Ranked Execution Order

Companion to `Funnel_Mode_QA_Test_Scenarios.md` (full steps/expected results/DB checks live there — this file just reorders the same ~155 test IDs by **how bad it is if the scenario reveals a real bug**, not by where the feature lives on the page). Test top-down if time is limited.

**Severity model** (per the QA skill): **S0** = checkout/payment broken, money wrong, legal/regulatory exposure, security bypass, data loss. **S1** = a core flow degrades for a real segment, has no full workaround, or is a compliance-adjacent gap. **S2** = non-core feature or validation issue, workaround exists. **S3** = cosmetic/content nuance, no functional or legal impact.

---

## S0 — Critical / Blocker (test these first, every time)

| ID | Scenario | Why it's S0 |
|---|---|---|
| FUN-1303 | Guest checkout full happy path (Card) | The entire funnel exists to produce this one flow. If it fails, zero revenue. |
| FUN-201 | Cold load happy path | If `/` itself doesn't render, the whole storefront's primary entry point is down for all traffic. |
| FUN-202 | Funnel product slug missing/unset | Same blast radius as FUN-201 — one admin misconfig takes down `/` for every visitor. |
| FUN-203 | Funnel product unpublished | Identical failure mode to FUN-202. |
| FUN-105 | Only `/` affected by funnel toggle | If this regresses, toggling funnel mode could break cart/checkout/other routes site-wide. |
| FUN-712 | Fallback single-variant button when all 4 packages go stale | This is the last line of defense — if it's also broken, literally nobody can buy anything from the funnel. |
| FUN-1316 | Payment step offers Card only, no leftover COD | COD was added then reverted twice already; a surviving option customers can select but that can't actually be processed is a broken payment promise. |
| FUN-1404 | Card-only claim consistency end-to-end | Same COD-revert risk, traced across every surface (copy, checkout, emails, admin) — highest-recurrence regression in this codebase's recent history. |
| FUN-1317 | Stored payment methods — guest bypass check | Security: an unauthenticated guest forcing a `stored_payment_method_id` would mean unauthorized use of someone else's stored card. |
| FUN-1318 | iCard modal — success path | The literal moment money changes hands; any failure here is a lost sale or a customer charged with no order. |
| FUN-1321 | Payment gateway misconfigured — recovery | If recovery is broken, a bad deploy/config blocks 100% of sales with no way for a customer to complete a paid order. |
| FUN-1322 | Duplicate order submission protection | Failure here means a customer is charged twice for one intended purchase — direct financial harm + refund burden. |
| FUN-1325 | Two tabs, concurrent checkout, same guest cart | Same double-charge/duplicate-order class of risk as FUN-1322, via a different trigger. |
| FUN-1307 | BOX NOW free-shipping promo reflected correctly at checkout | Wrong shipping charge = customer billed for something explicitly advertised as free — billing dispute + false-advertising exposure. |
| FUN-1401 | BOX NOW free-shipping promo expiry boundary (2026-09-30) | Landing-page badge, checkout price, and order total must flip together at the exact boundary — a live false-advertising/billing-mismatch risk one month out. Retest around the actual date. |
| FUN-801 | Delivery/Payment/Returns trust items accuracy | False or stale trust claims here are a direct чл. 68д ЗЗП / EU UCPD consumer-protection exposure — this exact class of bug happened before (the historical free-shipping badge). |
| FUN-1110 | BOX NOW floating badge accuracy vs. promo state | Same false-advertising exposure as FUN-801, on the page's most persistent, always-visible claim. |
| FUN-1501 | Admin funnel endpoints — authorization | Unauthorized access to `/admin/funnel` means anyone could toggle the storefront's homepage or rewrite its pricing/content. |
| FUN-1805 | iCard modal/redirect flow across browsers (esp. Safari) | If payment silently breaks on an entire browser (Safari is a large share of mobile traffic), that's a revenue-blocking outage for a whole customer segment that's easy to miss testing only in Chrome. |

---

## S1 — High (core flow degraded for a real segment, or a compliance gap)

| ID | Scenario | Why it's S1 |
|---|---|---|
| FUN-1901 | Keyboard-only navigation, full funnel + checkout | Assistive-tech users are fully blocked from ever completing a purchase if this fails. |
| FUN-1902 | Screen reader pass on pricing/FAQ/checkout | Same segment-blocking impact as FUN-1901. |
| FUN-2001 | Slow 3G across the full journey | Common condition for mobile ad traffic; a checkout that breaks under real-world network conditions silently loses a meaningful revenue segment. |
| FUN-1604 | Lead deletion (GDPR erasure) | A real regulatory compliance requirement — failing genuine erasure is a legal exposure, not just a UX gap. |
| FUN-1314 | Review step: legal document acceptance gating | If bypassable, an order can be placed without the customer accepting required legal terms — contract-formation/compliance risk. |
| FUN-1315 | Order Review step displays fully accurate summary | The customer's last accurate look at what they're paying for before money moves — errors here directly cause disputes/chargebacks. |
| FUN-1330 | Order confirmation email accuracy | Customer-facing financial/legal document (order total, items) — errors are dispute-generating. |
| FUN-1329 | Order confirmation page after refresh/token loss | A customer who reflexively refreshes right after paying can get locked out of viewing their own just-placed order — real, common, support-generating. |
| FUN-1327 | Legal documents fail to load at Review step | If the legal-docs API is down, no one can pass Review — an outage in a dependency silently blocks 100% of checkouts. |
| FUN-1324 | Refresh mid-checkout, especially with `activePayment` already set | Risk of an order being created server-side that the customer can no longer see or pay for from the page they're on. |
| FUN-1319 | iCard decline — retry mints a genuinely fresh attempt | If retry reuses a dead transaction reference instead of a fresh one, the retry itself can fail against iCard's own duplicate-OrderID rejection. |
| FUN-1320 | iCard customer-cancelled — order/payment end state | An order stuck forever in `AwaitingPayment` with no cleanup is a real operational data-hygiene problem, even if not visibly "broken" to the customer. |
| FUN-1311 | Switching carrier mid-flow clears previously selected office | A leaked BOX NOW locker onto a Speedy order means a package could ship to the wrong location entirely — real fulfillment/customer harm. |
| FUN-1309 | Speedy address-delivery selection | One of only two shipping paths — if broken, half the funnel's delivery options are unusable. |
| FUN-1308 | BOX NOW locker selection required | The *default* shipping method — if broken, most customers hit this immediately. |
| FUN-1301 | Cart page after arriving from the funnel | The very next screen after every funnel add-to-cart; a broken cart here stops the conversion before checkout even starts. |
| FUN-1507 | Toggling funnel mode mid-checkout doesn't affect the in-progress session | An admin action should never be able to break a customer's purchase already underway. |
| FUN-1001 | Lead capture happy path | The funnel's secondary conversion goal (leads from non-buyers) — a real revenue-adjacent channel if broken. |
| FUN-1205 | Tracking never blocks the actual user-facing action | Guards against an entire class of bug where a blocked/failed pixel call could accidentally delay or break checkout itself. |
| FUN-711 | Package variant goes stale — public payload degrades gracefully | Protects against the funnel silently breaking for all visitors the moment an admin edits the catalog without updating `/admin/funnel`. |
| FUN-707 | Out-of-stock package: no dead click target | A broken CTA on an in-demand SKU directly blocks that purchase. |
| FUN-708 | Add-to-cart from a package: correct price, quantity, and navigation | Core add-to-cart correctness — wrong price added or wrong tracked value corrupts both the order and ad-spend optimization data. |
| FUN-709 | Rapid double-click / race protection on package Add | A race here silently doubles a customer's order quantity/charge without their intent. |
| FUN-501 | Admin content edits propagate + no unescaped HTML/XSS | If content is ever rendered unescaped, this becomes a live XSS vector reachable by anyone who can edit funnel content. |
| FUN-403 | Early vs. numbered Pricing sections behave identically | If they diverge, one entry point could add the wrong price/package to the cart. |
| FUN-302 | Hero "from price" = cheapest package, computed live | A visibly wrong headline price is a direct pricing-accuracy and trust issue on the page's most-seen number. |
| FUN-303 | Hero price fallback when no packages configured | Same pricing-accuracy stakes as FUN-302, in the degraded-config case. |
| FUN-204 | Funnel product has zero variants — graceful degrade | Tests whether a bad catalog state degrades safely or crashes the whole page for every visitor. |
| FUN-205 | Products API fails after settings load | A transient backend outage taking down the landing page's core content. |
| FUN-101 | Fresh install defaults funnel mode ON | Wrong default silently ships every new environment (including a real production reset) in the wrong mode with no one noticing. |
| FUN-102 | Admin toggle OFF reverts homepage | Core admin control over the site's primary landing experience. |
| FUN-103 | Admin toggle ON re-enables funnel | Same as FUN-102, the other direction. |

---

## S2 — Medium (non-core feature or validation gap, workaround usually exists)

| ID | Scenario |
|---|---|
| FUN-104 | Toggle live-effect / staleness in an already-open tab |
| FUN-106 | Navbar hides search/Favorites in funnel mode |
| FUN-107 | Navbar restored when funnel mode is OFF |
| FUN-108 | `/search` guard redirect race with settings loading |
| FUN-206 | Reviews API fails/empty — rest of page unaffected |
| FUN-207 | Public settings fail — dispatch/badge degrade correctly |
| FUN-210 | Scroll-reveal animation + reduced-motion handling |
| FUN-301 | Hero content edit propagation |
| FUN-304 | Dispatch promise cutoff boundary behavior |
| FUN-402 | No duplicate DOM ids between the two Pricing instances |
| FUN-503 | HowToUse video rendering / autoplay policy |
| FUN-504 | HowToUse PDF link live binding, dead-link risk |
| FUN-505 | Comparison section CTA/price consistency |
| FUN-506 | Fresh-seed content completeness across all 12 sections |
| FUN-603 | Reviews render safely, not secretly filtered by rating |
| FUN-604 | Product JSON-LD accuracy |
| FUN-703 | No 1-pack configured — no broken savings badges |
| FUN-704 | Per-unit price line accuracy |
| FUN-706 | Low-stock badge honesty |
| FUN-710 | Add-to-cart error surfaces without losing page state |
| FUN-902 | FAQ PDF upload admin trap (forgot to Save) |
| FUN-1002 | Welcome email sent only on first lead capture |
| FUN-1003 | Resubmission is idempotent (anti-enumeration) |
| FUN-1006 | Lead-capture rate limiting |
| FUN-1007 | Exit-intent trigger precision (dwell time, direction) |
| FUN-1008 | Exit-intent session persistence |
| FUN-1010 | Exit-intent is desktop-only |
| FUN-1012 | Exit-intent modal accessibility / focus trap |
| FUN-1013 | Lead-capture consent copy and link validity |
| FUN-1101 | Desktop sticky bar one-way reveal (possible doc/code mismatch) |
| FUN-1102 | Desktop bar never overlaps footer |
| FUN-1104 | Mobile sticky bar three-zone visibility logic |
| FUN-1106 | Sticky bars don't appear before data finishes loading |
| FUN-1111 | Z-index layering across chrome + exit modal |
| FUN-1201 | ViewContent fires once, correctly keyed |
| FUN-1202 | AddToCart event payload accuracy |
| FUN-1203 | Lead event fires once per successful submission |
| FUN-1204 | Full funnel event chain end-to-end |
| FUN-1302 | Adding a second package to an existing cart |
| FUN-1304 | Customer Info step field validation |
| FUN-1305 | Phone field normalization |
| FUN-1306 | BOX NOW default shipping pre-selection stability |
| FUN-1310 | Speedy office/locker alternative delivery types |
| FUN-1312 | Invoice/VAT opt-in and billing-address conditional logic |
| FUN-1323 | Back-button behavior during checkout |
| FUN-1328 | Settlement list load failure (alt carriers still work) |
| FUN-1402 | No dormant €25-threshold shipping logic resurfacing |
| FUN-1405 | Admin packages config must total exactly 4 |
| FUN-1406 | Package variant must belong to the configured product |
| FUN-1502 | Admin action logging completeness |
| FUN-1503 | Admin sees raw/stale config (actionable, unlike public payload) |
| FUN-1504 | Content editor per-section save isolation |
| FUN-1602 | Leads search — substring match, injection safety |
| FUN-1603 | Leads CSV export completeness |
| FUN-1701 | No horizontal scroll at any viewport |
| FUN-1702 | Package cards reflow correctly across breakpoints |
| FUN-1703 | Sticky bars don't overlap critical content |
| FUN-1706 | Touch target sizing on mobile |
| FUN-1801 | IntersectionObserver features cross-browser |
| FUN-1904 | Color contrast (badges, sticky bars, low-stock text) |
| FUN-2002 | Offline mid-flow — honest error, no silent false success |

---

## S3 — Low (cosmetic, content nuance, or explicitly non-functional)

| ID | Scenario |
|---|---|
| FUN-109 | Deep link `/search?q=` while funnel mode is ON |
| FUN-208 | Hash-anchor navigation on real page load |
| FUN-209 | `#usage-guide` pseudo-anchor fallback to wrong FAQ item |
| FUN-305 | Hero CTA scroll target |
| FUN-306 | Hero exempt from scroll-reveal |
| FUN-401 | Early pricing box omits subtitle/sales note (by design) |
| FUN-502 | UseCasesSection is fixed copy, not admin-editable (by design) |
| FUN-601 | Review summary hero line only with real reviews |
| FUN-602 | Testimonials carousel shows full page-1, not capped at 3 |
| FUN-605 | FAQ JSON-LD matches visible FAQ |
| FUN-701 | Featured card is always the 5-pack |
| FUN-705 | Package image fallback for unmapped pack sizes |
| FUN-802 | BOX NOW badge anchor target |
| FUN-901 | FAQ accordion open/close behavior |
| FUN-903 | FAQ retitle breaking the usage-guide match |
| FUN-904 | FAQ item order/count changes |
| FUN-905 | Long/short/empty FAQ answers |
| FUN-1004 | Email normalization (case-insensitivity) |
| FUN-1005 | Lead-capture client-side validation |
| FUN-1009 | Exit-intent fail-safe on storage-restricted browsers |
| FUN-1011 | Exit-intent modal close mechanisms |
| FUN-1014 | Duplicate lead-capture instances don't double-fire |
| FUN-1103 | Desktop bar content correctness (thumbnail/name/rating) |
| FUN-1105 | Mobile bar CTA scroll behavior |
| FUN-1107 | Badge portal positioning cross-browser |
| FUN-1108 | Badge admin toggle |
| FUN-1109 | Badge click target |
| FUN-1313 | VAT number format validation (acknowledged product gap, not a bug) |
| FUN-1326 | Empty cart reaching `/checkout` directly |
| FUN-1403 | Dual EUR/BGN display (hardcoded but currently correct) |
| FUN-1505 | Admin content editor rejects unknown section key |
| FUN-1506 | Concurrent admin edits — last-write-wins |
| FUN-1601 | Leads list pagination/ordering |
| FUN-1605 | Re-subscribing a lead after deletion |
| FUN-1704 | Badge/mobile-bar overlap at small viewports |
| FUN-1705 | Exit-intent modal sizing on narrow desktop widths |
| FUN-1802 | Badge portal — Firefox-specific check |
| FUN-1803 | sessionStorage restrictions on Safari |
| FUN-1804 | Modern CSS graceful degradation |
| FUN-1903 | Image alt text audit |
| FUN-2003 | Settlement list (~1MB) load impact |
| FUN-2004 | Image weight/lazy-loading, hero LCP |

---

**Note on the two checklist sections in the full document** (§21 Data-Integrity/DB-Verification and §22 Regression Checklist) — these aren't standalone test cases, they're aggregations that reference the IDs above. Run §21's DB spot-checks after any session that touched S0/S1 items; run §22 after any funnel-related deploy regardless of what else you test.

**Suggested execution order:** all of S0 first (19 items — these are the ones where a real bug would actually hurt), then S1 (32 items), then S2/S3 as time allows. If you can only run one pass before a release, S0 + S1 (51 items) is the actual release-readiness gate; S2/S3 are polish.
