<?php

namespace App\Services;

use App\DataTransferObjects\Admin\DiscountCodeData;
use App\Models\DiscountCode;

/**
 * Admin CRUD for discount codes - deliberately separate from
 * DiscountCodeService, which owns the customer-facing validate/redeem path
 * at checkout and has nothing to do with how a code gets created. Every
 * code made through this screen is a plain marketing code: customer_email
 * and review_id stay null (both are set only by IssueReviewRewardCode for
 * a review-reward code, which this screen doesn't touch).
 */
class DiscountCodeAdminService
{
    public function create(DiscountCodeData $data): DiscountCode
    {
        return DiscountCode::create([
            'code' => $data->code,
            'discount_percent' => $data->discountPercent,
            'max_uses' => $data->maxUses,
            'starts_at' => $data->startsAt,
            'expires_at' => $data->expiresAt,
            'is_active' => $data->isActive,
        ]);
    }

    public function update(DiscountCode $discountCode, DiscountCodeData $data): DiscountCode
    {
        $discountCode->update([
            'code' => $data->code,
            'discount_percent' => $data->discountPercent,
            'max_uses' => $data->maxUses,
            'starts_at' => $data->startsAt,
            'expires_at' => $data->expiresAt,
            'is_active' => $data->isActive,
        ]);

        return $discountCode->fresh();
    }

    /**
     * Safe to hard-delete unlike most other admin-managed records here -
     * orders.discount_code is a plain string snapshot of the code typed at
     * checkout (see its own migration), not a foreign key, so a historical
     * order's record of what it redeemed survives this untouched.
     */
    public function delete(DiscountCode $discountCode): void
    {
        $discountCode->delete();
    }
}
