# Wallet payments (Apple Pay / Google Pay)

## Architecture

Apple Pay and Google Pay are **payment methods**, not separate payment
providers. Every payment — card, Apple Pay, or Google Pay — still goes
through the single existing `PaymentGatewayInterface` implementation,
`ICardPaymentGateway`, and the same iCard IPGPurchase hosted-redirect flow
already used for card payments. Nothing about the payment architecture was
rewritten or duplicated:

- `App\Enums\PaymentProvider` is unchanged (`icard` is still the only
  case) — "provider" means "which gateway integration", and there's still
  only one.
- `App\Enums\PaymentMethod` is new: `card`, `apple_pay`, `google_pay` —
  "method" means "which instrument the customer pays with", independent
  of provider.
- `payments.payment_method` and `payment_transactions.payment_method`
  columns record which method was used, defaulting to `card` for
  backward compatibility with every payment that existed before this.
- `PaymentGatewayInterface::createSession()` gained one new parameter
  (`PaymentMethod $method`). `ICardPaymentGateway::createSession()` for
  `PaymentMethod::Card` behaves **identically** to before — zero risk to
  the working card flow. For `apple_pay`/`google_pay`, one additional
  form field is added to the same signed IPGPurchase field set (see
  "What's unverified" below).
- Webhook handling (`PaymentService::handleWebhook`) needed **no
  changes** — iCard's callback shape doesn't vary by payment method, and
  the payment's method is already known from the `payments` row looked
  up by `provider_reference`. Idempotency (dedup by request-body hash)
  works identically regardless of method.

## Configuration

Four flags control availability. **A wallet method only appears at
checkout when both of its flags are `true`:**

| Variable | Purpose |
|---|---|
| `APPLE_PAY_ENABLED` | App-level master switch for Apple Pay |
| `GOOGLE_PAY_ENABLED` | App-level master switch for Google Pay |
| `ICARD_APPLE_PAY_ENABLED` | iCard-specific: "the iCard merchant account has Apple Pay configured" |
| `ICARD_GOOGLE_PAY_ENABLED` | iCard-specific: "the iCard merchant account has Google Pay configured" |

Why two flags per wallet instead of one: the app-level flag is a
product/feature decision ("do we want to offer this at all"); the
iCard-specific flag reflects whether iCard's own merchant configuration
actually supports it yet. Splitting them means the app can be "ready" for
a wallet before iCard-side setup is finished, without either side lying
about the other's readiness.

Additional placeholders (not credentials — see `.env`):

```
APPLE_PAY_MERCHANT_ID=
APPLE_PAY_MERCHANT_DOMAIN=
GOOGLE_PAY_ENVIRONMENT=TEST
GOOGLE_PAY_MERCHANT_ID=
```

All six default to disabled/empty. Nothing wallet-related activates
unless explicitly configured.

The full list is checked server-side in
`PaymentService::availablePaymentMethods()` and surfaced to the frontend
via `GET /api/v1/checkout/payment-methods` — the frontend never guesses
availability itself, it only renders what that endpoint returns.

## What's verified vs. what's not

Unlike the Speedy shipping integration (Sprint 11.5's sibling work),
**there is no way to test Apple Pay or Google Pay against a real account
in this environment** — both require infrastructure that doesn't exist
here (see "Production setup" below), regardless of what iCard's API
needs.

- **Verified**: the card payment flow is completely unaffected (451
  backend tests pass, including the full existing payment suite). The
  `payment_method` plumbing — validation, storage on `payments` and
  `payment_transactions`, the `/checkout/payment-methods` endpoint,
  config-gating, retry-with-different-method — is fully tested with
  mocked HTTP responses.
- **NOT verified**: the exact IPGPurchase field iCard's hosted page
  expects to restrict/pre-select a wallet method. `ICardPaymentGateway::walletFields()`
  sends a placeholder field (`PayMethod: ApplePay` / `PayMethod: GooglePay`)
  modeled on how comparable hosted-checkout gateways handle this — it is
  **not confirmed against iCard's actual documentation or a real iCard
  wallet-enabled merchant account**. This code path is inert today (both
  `ICARD_*_ENABLED` flags default false), so it carries zero risk to
  what's working — but **before enabling either wallet flag in
  production, confirm the real field name/values with iCard support** and
  update `walletFields()` accordingly.

## Production setup still required

Enabling these flags in `.env` is not sufficient to actually accept
wallet payments. Each wallet has its own real-world prerequisites,
independent of anything in this codebase:

**Apple Pay:**
1. An Apple Developer account and a registered **Apple Merchant ID**.
2. A **domain verification file** hosted at
   `https://<your-domain>/.well-known/apple-developer-merchantid-domain-association`
   on the real production domain — Apple checks this before any Apple
   Pay button will render, and it must be served over real HTTPS (not
   localhost).
3. Confirmation from iCard that Apple Pay is provisioned on the merchant
   account, and their real field/parameter name for it (see above).
4. Apple Pay only renders in Safari (macOS/iOS) — there is no way around
   this; it's a platform restriction, not a configuration option.

**Google Pay:**
1. A **Google Pay Business Console** account and merchant ID.
2. Pairing that merchant ID with iCard as the **payment gateway** in
   Google's console (Google Pay integrations are configured per-gateway,
   not generic).
3. `GOOGLE_PAY_ENVIRONMENT=TEST` can be used for early integration
   testing without full business verification; production traffic
   requires `PRODUCTION` plus the completed business verification.
4. Confirmation from iCard of their real field/parameter name (same
   caveat as Apple Pay above).

**Both:** iCard support should confirm whether their hosted IPGPurchase
page renders the actual wallet button itself once these are configured
(most likely, based on how comparable hosted-checkout gateways work), or
whether a different, tokenized API path is required instead of the
redirect flow this integration uses. That answer determines whether
`walletFields()` is the whole remaining fix or whether a materially
different flow is needed for wallets specifically.

## Frontend

`PaymentStep` (checkout's payment step) fetches available methods from
`/checkout/payment-methods` and renders them as a radio-card selector —
deliberately **not** real Apple Pay/Google Pay button widgets, since
those require the official SDKs this project doesn't integrate (per the
sprint's explicit instruction not to fake them). The actual wallet UI, if
any, would render on iCard's own hosted page after redirect.

One honest, zero-SDK capability check is included: Apple Pay is greyed
out in browsers without `window.ApplePaySession` (a standard, built-in
Safari API — no script load required to check for it), since it could
never work there regardless of configuration. Google Pay has no
equivalent check without loading Google's SDK, so it's left selectable
whenever the backend reports it enabled.
