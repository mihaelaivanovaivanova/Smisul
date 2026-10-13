<?php

namespace App\Services;

use App\Models\DiscountCode;
use Illuminate\Support\Str;

/**
 * Two callers, two different trust levels: CheckoutController's validate-
 * only endpoint (pre-order, just UI feedback - never redeems anything) and
 * OrderService::placeOrder() (the only place a code is ever actually
 * redeemed, re-validated fresh from the DB inside that same locked
 * transaction - see that method's own comment on never trusting
 * client-supplied discount figures, which this mirrors for codes).
 *
 * A code is redeemable by whoever has it, regardless of which email is
 * typed at checkout - discount_codes.customer_email (set when
 * IssueReviewRewardCode issues a review-reward code) is kept purely as a
 * record of who originally earned it, not as a redemption gate. By
 * request: the single-use limit (max_uses/used_count) is what actually
 * stops a code being reused, not an email match.
 */
class DiscountCodeService
{
    public function findValid(string $code): ?DiscountCode
    {
        $discountCode = DiscountCode::query()
            ->whereRaw('UPPER(code) = ?', [Str::upper(trim($code))])
            ->first();

        if ($discountCode === null || ! $discountCode->isRedeemable()) {
            return null;
        }

        return $discountCode;
    }

    /**
     * Row-locks the code so two concurrent checkout attempts can't both
     * redeem the last remaining use - the caller must already be inside a
     * DB transaction (OrderService::placeOrder() is). Re-runs the same
     * eligibility check as findValid() against the locked row, since
     * another request could have consumed it between an earlier
     * findValid() call (e.g. the checkout-page "Apply" preview) and now.
     */
    public function findRedeemable(string $code): ?DiscountCode
    {
        $discountCode = DiscountCode::query()
            ->whereRaw('UPPER(code) = ?', [Str::upper(trim($code))])
            ->lockForUpdate()
            ->first();

        if ($discountCode === null || ! $discountCode->isRedeemable()) {
            return null;
        }

        return $discountCode;
    }

    public function redeem(DiscountCode $discountCode): void
    {
        $discountCode->increment('used_count');
    }

    /**
     * A short, visually unambiguous code - uppercase letters + digits minus
     * 0/O/1/I (easy to type correctly from an email on a phone keyboard).
     * Collisions are astronomically unlikely at this length/volume, but
     * checked and retried anyway rather than trusting that.
     */
    public function generateUniqueCode(string $prefix = 'MISWAK'): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $code = "{$prefix}-{$suffix}";
        } while (DiscountCode::query()->whereRaw('UPPER(code) = ?', [$code])->exists());

        return $code;
    }
}
