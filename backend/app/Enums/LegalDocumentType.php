<?php

namespace App\Enums;

enum LegalDocumentType: string
{
    case TermsOfService = 'terms_of_service';
    /**
     * Covers both general privacy practices and GDPR-specific disclosures
     * (lawful basis, data subject rights, retention, transfers) as one
     * document — there used to be a separate GdprPolicy case, but a
     * GDPR-compliant Privacy Policy already covers everything that
     * document did, so keeping them apart was redundant. See
     * docs/legal-gdpr-seo.md.
     */
    case PrivacyPolicy = 'privacy_policy';
    case RightOfWithdrawal = 'right_of_withdrawal';
    case CookiePolicy = 'cookie_policy';
    case ShippingPolicy = 'shipping_policy';
    case ReturnsPolicy = 'returns_policy';

    public function label(): string
    {
        return match ($this) {
            self::TermsOfService => 'General Terms',
            self::PrivacyPolicy => 'Privacy Policy',
            self::RightOfWithdrawal => 'Right of Withdrawal',
            self::CookiePolicy => 'Cookie Policy',
            self::ShippingPolicy => 'Shipping Policy',
            self::ReturnsPolicy => 'Returns & Complaints Policy',
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
            self::CookiePolicy => 'cookie-policy',
            self::ShippingPolicy => 'shipping-policy',
            self::ReturnsPolicy => 'returns-policy',
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
     * of — unchanged from before ShippingPolicy/ReturnsPolicy existed, so
     * adding those two new informational-only page types doesn't turn
     * checkout into a 6-checkbox form. See LegalDocumentService.
     *
     * @return list<self>
     */
    public static function requiredAtCheckout(): array
    {
        return [self::TermsOfService, self::PrivacyPolicy, self::RightOfWithdrawal, self::CookiePolicy];
    }

    /**
     * The documents a registered account holder must have accepted in
     * their current version — the ongoing account relationship runs on
     * these two; withdrawal/cookie terms are order- and browse-scoped.
     *
     * @return list<self>
     */
    public static function requiredForAccount(): array
    {
        return [self::TermsOfService, self::PrivacyPolicy];
    }
}
