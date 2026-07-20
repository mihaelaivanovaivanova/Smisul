<?php

namespace App\Enums;

/**
 * Every distinct thing a user/visitor can grant or withdraw consent for.
 * Terms/Privacy mirror the LegalDocumentType rows they're tied to (see
 * Consent::legal_document_id); Marketing/Newsletter track the same intent
 * as the users.marketing_consent/newsletter_subscription boolean columns
 * but as an immutable, timestamped, IP/UA-stamped history instead of a
 * single mutable "current state" flag. The four Cookie* cases back the
 * cookie consent banner's categories.
 */
enum ConsentType: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';
    case Marketing = 'marketing';
    case Newsletter = 'newsletter';
    case CookieNecessary = 'cookie_necessary';
    case CookieAnalytics = 'cookie_analytics';
    case CookieMarketing = 'cookie_marketing';
    case CookiePreferences = 'cookie_preferences';

    public function label(): string
    {
        return match ($this) {
            self::Terms => 'Terms of Service',
            self::Privacy => 'Privacy Policy',
            self::Marketing => 'Marketing communications',
            self::Newsletter => 'Newsletter',
            self::CookieNecessary => 'Necessary cookies',
            self::CookieAnalytics => 'Analytics cookies',
            self::CookieMarketing => 'Marketing cookies',
            self::CookiePreferences => 'Preference cookies',
        };
    }

    /**
     * @return list<self>
     */
    public static function cookieCategories(): array
    {
        return [self::CookieNecessary, self::CookieAnalytics, self::CookieMarketing, self::CookiePreferences];
    }

    /**
     * The LegalDocumentType a consent type's Consent::legal_document_id
     * rows point at, so callers can look up "which document version does
     * this consent type's latest row need to match" without duplicating
     * the Terms/Privacy mapping — null for consent types that aren't tied
     * to a versioned legal document (Marketing, Newsletter, the four
     * cookie categories).
     */
    public function legalDocumentType(): ?LegalDocumentType
    {
        return match ($this) {
            self::Terms => LegalDocumentType::TermsOfService,
            self::Privacy => LegalDocumentType::PrivacyPolicy,
            default => null,
        };
    }
}
