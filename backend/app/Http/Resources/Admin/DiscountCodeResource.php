<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\DiscountCode
 */
class DiscountCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            // Set only for a review-reward code (IssueReviewRewardCode) -
            // null for one an admin created directly through this screen.
            'customer_email' => $this->customer_email,
            'discount_percent' => (float) $this->discount_percent,
            'max_uses' => $this->max_uses,
            'used_count' => $this->used_count,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_active' => $this->is_active,
            // Convenience flag so the admin list doesn't need to
            // re-derive "is this actually usable right now" client-side
            // (active + within its window + hasn't hit max_uses - see
            // DiscountCode::isRedeemable()).
            'is_redeemable' => $this->isRedeemable(),
            'review_id' => $this->review_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
