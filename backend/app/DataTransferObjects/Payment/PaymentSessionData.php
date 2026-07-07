<?php

namespace App\DataTransferObjects\Payment;

/**
 * What a gateway hands back after creating a payment session. iCard's real
 * IPG protocol has two genuinely different shapes depending on payment
 * method — this DTO carries both, discriminated by $mode:
 *
 *  - 'modal' (Card): a Token from IPGPaymentToken, used to load iCard's
 *    own hosted JS (modalJsUrl?token=...) which renders a payment overlay
 *    directly on the page — see ICardPaymentGateway::createModalSession()
 *    and components/checkout/IcardModal.tsx on the frontend.
 *  - 'wallet' (Apple Pay / Google Pay): no iCard API call has happened
 *    yet at this point — walletConfig is just the bootstrap data iCard's
 *    separate wallet SDK (ICardIpgGAPay) needs to render its own buttons
 *    and drive the two wallet-specific endpoints itself (see
 *    WalletPaymentController and components/checkout/IcardWalletButtons.tsx).
 *
 * providerReference is always $payment->transaction_reference, set here
 * and reused for every later iCard call this Payment makes (modal token
 * creation, wallet validation session, tokenized purchase) so the async
 * notify webhook can always match back to this Payment by OrderID.
 */
final readonly class PaymentSessionData
{
    /**
     * @param  array<string, mixed>|null  $walletConfig
     * @param  array<string, mixed>  $rawResponse
     */
    private function __construct(
        public string $mode,
        public string $providerReference,
        public ?string $modalToken = null,
        public ?string $modalJsUrl = null,
        public ?string $theme = null,
        public ?array $walletConfig = null,
        public array $rawResponse = [],
    ) {}

    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public static function modal(string $providerReference, string $token, string $modalJsUrl, string $theme, array $rawResponse = []): self
    {
        return new self(
            mode: 'modal',
            providerReference: $providerReference,
            modalToken: $token,
            modalJsUrl: $modalJsUrl,
            theme: $theme,
            rawResponse: $rawResponse,
        );
    }

    /**
     * @param  array<string, mixed>  $walletConfig
     */
    public static function wallet(string $providerReference, array $walletConfig): self
    {
        return new self(
            mode: 'wallet',
            providerReference: $providerReference,
            walletConfig: $walletConfig,
        );
    }
}
