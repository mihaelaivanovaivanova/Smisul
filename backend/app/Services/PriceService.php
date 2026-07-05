<?php

namespace App\Services;

use App\DataTransferObjects\PriceData;
use App\Events\Favorite\ProductPriceDropped;
use App\Models\Price;
use App\Models\PriceHistory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PriceService
{
    /**
     * Set (create or replace) a variant's price for a currency, appending a
     * PriceHistory row whenever the amount actually changes. Price rows are
     * never edited without a corresponding history entry — that's the one
     * invariant this whole entity pair exists to protect.
     */
    public function setPrice(
        ProductVariant $variant,
        PriceData $data,
        ?User $changedBy = null,
        ?string $reason = null,
    ): Price {
        $oldAmount = null;

        $price = DB::transaction(function () use ($variant, $data, $changedBy, $reason, &$oldAmount) {
            $existing = $variant->prices()->where('currency', $data->currency)->first();
            $oldAmount = $existing?->amount;

            $price = $variant->prices()->updateOrCreate(
                ['currency' => $data->currency],
                [
                    'amount' => $data->amount,
                    'compare_at_amount' => $data->compareAtAmount,
                ],
            );

            if ($oldAmount === null || (float) $oldAmount !== $data->amount) {
                PriceHistory::create([
                    'product_variant_id' => $variant->id,
                    'currency' => $data->currency,
                    'old_amount' => $oldAmount,
                    'new_amount' => $data->amount,
                    'changed_by' => $changedBy?->id,
                    'reason' => $reason,
                ]);
            }

            return $price;
        });

        // Dispatched after the transaction commits, not from inside it —
        // the listener sends mail, which shouldn't run against a price
        // change that might yet be rolled back.
        if ($oldAmount !== null && $data->amount < (float) $oldAmount) {
            event(new ProductPriceDropped($variant, (float) $oldAmount, $data->amount, $data->currency));
        }

        return $price;
    }
}
