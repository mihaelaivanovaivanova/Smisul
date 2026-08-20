# iCard payments: embedded modal (card) and wallet SDK (Apple Pay / Google Pay)

> **Status: superseded.** The separate `WalletPaymentController`,
> `IcardWalletButtons.tsx`, and dedicated wallet endpoints
> (`/payments/{order}/wallet/*`) this document describes were later
> reverted and no longer exist in the codebase. Apple Pay/Google Pay are
> currently rendered by iCard *inside* its own hosted modal, not as a
> separate application-owned flow — see `docs/icard-integration.md` for
> the current, accurate architecture. This document is kept for its
> historical technical detail (real IPG field shapes for
> `IPGTokenProviderSession`/`IPGTokenizedCardPurchase`) in case a
> standalone wallet SDK integration is attempted again.

## Architecture

Every payment method renders **entirely in-page** — the customer never
leaves this site, unlike the original redirect-based integration this
replaced (see "History" below).

- **Card**: `PaymentService::initiate()` calls
  `ICardPaymentGateway::createSession()`, which for `PaymentMethod::Card`
  calls iCard's `IPGPaymentToken` API server-to-server to get a
  short-lived `Token`. The frontend (`components/checkout/IcardModal.tsx`)
  loads iCard's own hosted JS (`modal_js_url?token=...`), which renders a
  payment overlay directly into a `#ipg` container on the checkout page.
  Success/failure/cancellation are reported via DOM `CustomEvent`s iCard's
  script dispatches on `document` (`ipg.payment.success`,
  `ipg.payment.error`, `ipg.user.cancel`, etc.) — see
  `api/payment.ts::loadIcardModalScript`.
- **Apple Pay / Google Pay**: `createSession()` makes **no iCard API call
  at all** for these — it just returns the config iCard's separate wallet
  SDK (`ICardIpgGAPay`, loaded from `wallet_js_url`) needs to render its
  own buttons (`components/checkout/IcardWalletButtons.tsx`). The wallet
  SDK itself drives the next two calls once the customer actually taps the
  button:
  - `IPGTokenProviderSession` (Apple's merchant-validation step) via
    `POST /payments/{order}/wallet/token-provider-session`.
  - `IPGTokenizedCardPurchase` (the actual charge, once Apple/Google hand
    back a tokenized card) via
    `POST /payments/{order}/wallet/tokenized-card-purchase`.

  Both are handled by `WalletPaymentController` → new `PaymentService`
  methods → `ICardPaymentGateway`, and proxy iCard's raw response back to
  the wallet SDK verbatim (the SDK expects to parse it itself).
- **Webhook handling needed no changes.** iCard's async notify callback
  (`Payment.OrderId`/`Payment.Status`/`Operation.Type`/`Status`) is
  identical regardless of how the payment was initiated — modal or
  wallet, both resolve through the exact same
  `PaymentService::handleWebhook()` that was already fully verified. The
  tokenized-card-purchase response only *acknowledges receipt*
  (`Status: "0"` = accepted for processing); the real terminal outcome
  still only ever arrives via that webhook, so `payment.status` is
  deliberately left untouched by both new wallet endpoints.
- `PaymentSessionData` (the gateway → service DTO) now carries one of two
  shapes, discriminated by `$mode`: `'modal'` (a token + the modal JS URL)
  or `'wallet'` (bootstrap config, no token yet). There is no more
  `actionUrl`/`formFields` redirect-form shape.

## Configuration

Same two flags as before control wallet availability — nothing changed
here:

| Variable | Purpose |
|---|---|
| `APPLE_PAY_ENABLED` / `GOOGLE_PAY_ENABLED` | App-level master switch |
| `ICARD_APPLE_PAY_ENABLED` / `ICARD_GOOGLE_PAY_ENABLED` | iCard-specific: the merchant account actually has the wallet configured |

New config for the modal/wallet SDK's own hosted JS (sandbox URLs default
correctly, override for production):

```
ICARD_MODAL_JS_URL=https://dev-ipg.icards.eu/sandbox/js/payment-modal.js
ICARD_WALLET_JS_URL=https://dev-ipg.icards.eu/sandbox/js/icard-g-a-pay.min.js
```

`APPLE_PAY_MERCHANT_ID`/`APPLE_PAY_MERCHANT_DOMAIN`/
`GOOGLE_PAY_ENVIRONMENT`/`GOOGLE_PAY_MERCHANT_ID` are unchanged — see
"Production setup still required" below.

## What's verified vs. what's not

- **Verified against Smisul's own real iCard sandbox credentials**: the
  card modal flow. A real order placed through the running dev
  environment received a genuine `Token` minted by iCard's actual
  sandbox server, with the signed request and response-signature
  verification both succeeding end-to-end — the same standard of
  verification the Speedy shipping integration met earlier in this
  project. The full backend test suite (472 tests) covers the request
  shape, response wiring, and config-gating with mocked HTTP responses.
- **Field shapes are no longer a guess** — they're taken from a working
  reference implementation of the same iCard IPG API (the exact
  `IPGmethod`/field names for `IPGPaymentToken`, `IPGTokenProviderSession`,
  and `IPGTokenizedCardPurchase`), not invented from general hosted-
  checkout conventions like the old `PayMethod` field this replaced.
- **NOT verified**: the wallet SDK's actual tap-to-pay flow. `ICardIpgGAPay`
  is a minified, source-unavailable third-party script — its exact
  request-body field names for the two endpoints it calls
  (`tokenProviderSessionUrl`/`processPaymentUrl`) are inferred from the
  reference implementation's own defensive, multiple-casing-variant
  field extraction, not from official documentation. `WalletPaymentController`
  mirrors that same defensive extraction for the same reason. Testing the
  actual button rendering and a real tap-to-pay transaction requires a
  browser with Apple Pay or Google Pay actually configured (real Apple/
  Google hardware or an equivalent test setup), which doesn't exist in
  this environment — **a manual click-through with real wallet-capable
  hardware is recommended before enabling either wallet flag in
  production.**

## Production setup still required

Same real-world prerequisites as before, independent of anything in this
codebase:

**Apple Pay:**
1. An Apple Developer account and a registered **Apple Merchant ID**.
2. A **domain verification file** hosted at
   `https://<your-domain>/.well-known/apple-developer-merchantid-domain-association`
   on the real production domain, served over real HTTPS.
3. Confirmation from iCard that Apple Pay is provisioned on the merchant
   account.
4. Apple Pay only renders in Safari (macOS/iOS) — a platform restriction,
   not a configuration option.

**Google Pay:**
1. A **Google Pay Business Console** account and merchant ID, paired with
   iCard as the payment gateway in Google's console.
2. `GOOGLE_PAY_ENVIRONMENT=TEST` for early integration testing;
   `PRODUCTION` requires completed business verification.

## Frontend

`PaymentStep` (checkout's payment step) still just fetches available
methods from `/checkout/payment-methods` and renders a preference
selector. Once "pay" is pressed, `CheckoutPage` places the order and
swaps that step's content for either `IcardModal` (card) or
`IcardWalletButtons` (Apple/Google Pay) — the customer stays on
`/checkout` throughout. On success, `CheckoutPage` navigates to
`/order-confirmation/:orderId` exactly as before. On error/cancel, an
inline retry re-calls the existing `initiatePayment()` retry endpoint to
mint a fresh attempt for the same order, without re-submitting the whole
checkout form.

Apple Pay's zero-SDK availability check
(`window.ApplePaySession`) is unchanged.

## History

This integration originally redirected the customer's browser to iCard's
own hosted page via a signed `IPGPurchase` form-POST
(`PaymentSessionData.actionUrl`/`formFields`), with a single unverified
`PayMethod` field guessed for wallets. That flow, its dedicated
`/payment/success`, `/payment/failed`, and `/payment/cancelled` result
pages, and the `ICARD_RETURN_URL`/`ICARD_CANCEL_URL` config were removed
entirely when this modal/wallet-SDK integration replaced it — they have
no equivalent in the new architecture, since nothing ever navigates away
from the site anymore.
