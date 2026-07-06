<?php

namespace App\Http\Resources\Admin;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status->value,
            'verified_purchase' => $this->verified_purchase,
            'helpful_count' => $this->helpful_count,
            'created_at' => $this->created_at->toIso8601String(),
            'customer' => [
                'id' => $this->user->id,
                'name' => $this->user->fullName(),
                'email' => $this->user->email,
            ],
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
            ],
            'order_id' => $this->order_id,
            'admin_reply' => $this->admin_reply,
            'admin_reply_at' => $this->admin_reply_at?->toIso8601String(),
            'admin_replied_by' => $this->whenLoaded('adminRepliedBy', fn () => $this->adminRepliedBy?->fullName()),
        ];
    }
}
