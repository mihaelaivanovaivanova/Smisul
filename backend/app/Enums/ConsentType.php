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
}
