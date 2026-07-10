# iCard IPG 4.5 integration

The implementation follows `IPG API BM ECommerce`, protocol 4.5, document
revision 3. It keeps card PAN/CVC outside this application: card entry is
hosted by iCard's modal. Apple Pay and Google Pay are enabled and rendered
inside that same hosted modal by iCard; they are not separate checkout
methods or application-owned wallet flows.

## Configuration

Administrators configure two independent profiles under **Settings →
Payments**: `sandbox` and `production`. Each profile has its own MID,
Originator, key indices, API/JavaScript URLs, merchant private RSA key and
iCard public RSA key. PEM keys are encrypted with Laravel's `APP_KEY`; they
are never returned by the API after saving. Switching the active profile
does not overwrite either profile.

Default endpoints:

| Environment | API | Modal | Wallet SDK |
|---|---|---|---|
| Sandbox | `https://dev-ipg.icards.eu/sandbox/` | `/sandbox/js/payment-modal.js` | `/sandbox/js/icard-g-a-pay.min.js` |
| Production | `https://ipg.icard.com/` | `/js/payment-modal.js` | `/js/icard-g-a-pay.min.js` |

The callback URL is normally
`https://smisul.bg/api/v1/payments/webhook/icard`. Configure the callback IP
allow-list includes `82.119.81.211`. Every callback still requires a valid
RSA signature; passing the IP check alone never changes a payment.

The SuperHosting archive contains a one-time private import prepared from
the proven MiswakWebsite sandbox profile. `install.php` imports it through
Laravel's encrypted model cast and deletes the temporary server-side file.
Production stays empty until its own credentials are entered in the admin.

## Supported flows

- Card purchase: `IPGPaymentToken` + `ModalType=IPGPurchase`.
- Store card: a signed callback containing
  `Operation.StoreCard.CardToken` stores only the encrypted 64-character
  token and masked card metadata for an authenticated customer.
- Stored card purchase: `IPGPaymentToken` +
  `ModalType=IPG3DSPurchaseWithStoredCard`, `VerifyCVC=1`.
- Apple Pay / Google Pay: displayed by iCard inside the regular
  `IPGPurchase` modal when enabled for the merchant MID.
- Reversal: admin-only `IPGReversal`, available before order processing.
- Partial/full refund: admin-only `IPGRefund`; a full refund transitions the
  payment and order to `refunded`.

The provider transaction reference is taken from the signed callback field
`Operation.Provider.Trn` and is required for reversal/refund.

## Callback rules

Callbacks are accepted as JSON or form-encoded POST requests. The handler:

1. checks the configured iCard source IP allow-list, including
   `82.119.81.211`;
2. verifies the RSA SHA-256 signature using the public key belonging to the
   environment in which the payment was created;
3. deduplicates valid deliveries by a SHA-256 hash of the raw body;
4. rejects repeated invalid-signature deliveries instead of acknowledging
   them;
5. verifies amount and numeric currency before changing payment state;
6. records every delivery and transaction for audit;
7. stores operation status/code/message, approval code, provider TRN,
   masked PAN and card type when supplied;
8. returns HTTP 200 only after an accepted callback is handled.

## Modal troubleshooting

The checkout API now returns a specific HTTP 502 message instead of a plain
`Server Error` when iCard rejects session creation. Check, in order:

1. the selected environment is enabled and has both PEM keys;
2. MID is 15 characters and matches the selected currency;
3. Originator, KeyIndex and KeyIndexResp match iCard's kit;
4. customer phone is normalized in the same way as the working
   MiswakWebsite integration;
5. callback URL is public HTTPS and resolves to the Laravel endpoint;
6. sandbox credentials are not used against the production URL (or vice
   versa).

Requests use the same natural-sort canonical RSA-SHA256 signature and
RFC3986 form encoding as MiswakWebsite. Gateway diagnostics never store the
private key, raw card data, card tokens or full signatures.
