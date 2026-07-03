<?php

namespace App\Enums;

enum LegalDocumentType: string
{
    case TermsOfService = 'terms_of_service';
    case PrivacyPolicy = 'privacy_policy';
    case GdprPolicy = 'gdpr_policy';
    case RightOfWithdrawal = 'right_of_withdrawal';
    case CookiePolicy = 'cookie_policy';

    public function label(): string
    {
        return match ($this) {
            self::TermsOfService => 'General Terms',
            self::PrivacyPolicy => 'Privacy Policy',
            self::GdprPolicy => 'GDPR Policy',
            self::RightOfWithdrawal => 'Right of Withdrawal',
            self::CookiePolicy => 'Cookie Policy',
        };
    }
}
