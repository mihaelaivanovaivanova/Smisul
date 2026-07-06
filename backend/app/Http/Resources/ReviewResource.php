<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-facing shape — used both for a product's public review list (where
 * every row is already pre-filtered to approved) and for a customer's own
 * "my reviews" list (where status/every row regardless of moderation state
 * is relevant, so `status` is included whenever the viewer owns the row).
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerId = $request->user()?->id;
        $isOwn = $viewerId !== null && $viewerId === $this->user_id;

        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'author_name' => $this->authorDisplayName(),
            'verified_purchase' => $this->verified_purchase,
            'helpful_count' => $this->helpful_count,
            'created_at' => $this->created_at->toIso8601String(),
            'admin_reply' => $this->admin_reply,
            'admin_reply_at' => $this->admin_reply_at?->toIso8601String(),
            'is_own' => $isOwn,
            'status' => $this->when($isOwn, fn () => $this->status->value),
            'order_id' => $this->when($isOwn, fn () => $this->order_id),
        ];
    }

    /**
     * First name + last initial — full last names aren't shown publicly.
     */
    private function authorDisplayName(): string
    {
        $user = $this->user;
        $lastInitial = $user->last_name !== '' ? mb_substr($user->last_name, 0, 1).'.' : '';

        return trim("{$user->first_name} {$lastInitial}");
    }
}
