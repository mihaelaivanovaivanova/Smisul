<?php

namespace App\Enums;

enum LegalDocumentType: string
{
    case TermsOfService = 'terms_of_service';
    /**
     * Covers general privacy practices, GDPR-specific disclosures (lawful
     * basis, data subject rights, retention, transfers), AND cookies as one
     * document — there used to be separate GdprPolicy and CookiePolicy
     * cases, but a GDPR-compliant Privacy Policy already covers everything
     * either of those did (cookies are just another category of data
     * processing, disclosed the same way), so keeping them apart was
     * redundant. See docs/legal-gdpr-seo.md. The cookie-consent banner
     * (accept/reject/customize) is unaffected — that's a separate runtime
     * mechanism (CookieConsentContext/ConsentService), not this document.
     */
    case PrivacyPolicy = 'privacy_policy';
    /**
     * Covers both the 14-day statutory right of withdrawal AND returns/
     * complaints (voluntary 30-day return window, the 2-year legal
     * guarantee of conformity, how to file a complaint) as one document —
     * there used to be a separate ReturnsPolicy case, but both answer the
     * same customer question ("how do I get my money back / report a
     * problem") and merging them means the legal-guarantee disclosure is
     * now part of the checkout-required acknowledgment (чл. 47, ал. 1, т.
     * 12 ЗЗП requires pre-contract disclosure of the legal guarantee, same
     * as the withdrawal right in т. 8 — ReturnsPolicy being
     * informational-only and checkout-optional was actually a compliance
     * gap on that point).
     */
    case RightOfWithdrawal = 'right_of_withdrawal';
    case ShippingPolicy = 'shipping_policy';

    public function label(): string
    {
        return match ($this) {
            self::TermsOfService => 'General Terms',
            self::PrivacyPolicy => 'Privacy Policy',
            self::RightOfWithdrawal => 'Right of Withdrawal, Returns & Complaints',
            self::ShippingPolicy => 'Shipping Policy',
        };
    }

    /**
     * A URL-safe identifier for public legal pages (/legal/{slug}) —
     * distinct from ->value only in spelling convention (hyphens instead
     * of underscores), kept as an explicit map rather than a string
     * transform so a future enum case can't silently produce a URL that
     * collides with something else.
     */
    public function slug(): string
    {
        return match ($this) {
            self::TermsOfService => 'terms-of-service',
            self::PrivacyPolicy => 'privacy-policy',
            self::RightOfWithdrawal => 'right-of-withdrawal',
            self::ShippingPolicy => 'shipping-policy',
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->slug() === $slug) {
                return $case;
            }
        }

        return null;
    }

    /**
     * The subset of document types checkout actually requires acceptance
     * of. ShippingPolicy stays informational-only/optional deliberately —
     * its content (carrier list, promo pricing/dates) can change often,
     * and requiring re-acceptance on every such change would spam every
     * existing account holder via NotifyAccountHoldersOfLegalDocumentUpdate
     * for something that isn't really a legal-terms change. See
     * LegalDocumentService.
     *
     * @return list<self>
     */
    public static function requiredAtCheckout(): array
    {
        return [self::TermsOfService, self::PrivacyPolicy, self::RightOfWithdrawal];
    }

    /**
     * The documents a registered account holder must have accepted in
     * their current version — the ongoing account relationship runs on
     * these two; the withdrawal/returns terms are order-scoped instead.
     *
     * @return list<self>
     */
    public static function requiredForAccount(): array
    {
        return [self::TermsOfService, self::PrivacyPolicy];
    }
}
