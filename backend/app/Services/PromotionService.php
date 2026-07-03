<?php

namespace App\Services;

use App\DataTransferObjects\PromotionData;
use App\Models\Promotion;

class PromotionService
{
    public function create(PromotionData $data): Promotion
    {
        $promotion = Promotion::create($this->baseAttributes($data));

        return $this->syncScopeAndReload($promotion, $data);
    }

    public function update(Promotion $promotion, PromotionData $data): Promotion
    {
        $promotion->update($this->baseAttributes($data));

        return $this->syncScopeAndReload($promotion, $data);
    }

    public function delete(Promotion $promotion): void
    {
        $promotion->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function baseAttributes(PromotionData $data): array
    {
        return [
            'name' => $data->name,
            'description' => $data->description,
            'type' => $data->type,
            'value' => $data->value,
            'code' => $data->code,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'usage_limit' => $data->usageLimit,
            'is_active' => $data->isActive,
        ];
    }

    private function syncScopeAndReload(Promotion $promotion, PromotionData $data): Promotion
    {
        $promotion->products()->sync($data->productIds);
        $promotion->categories()->sync($data->categoryIds);

        return $promotion->refresh()->load(['products', 'categories']);
    }
}
