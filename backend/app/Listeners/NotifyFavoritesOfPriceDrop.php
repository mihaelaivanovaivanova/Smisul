<?php

namespace App\Listeners;

use App\Events\Favorite\ProductPriceDropped;
use App\Models\Favorite;
use App\Notifications\PriceDropNotification;
use Illuminate\Support\Facades\Notification;

class NotifyFavoritesOfPriceDrop
{
    public function handle(ProductPriceDropped $event): void
    {
        $users = Favorite::query()
            ->where('product_variant_id', $event->variant->id)
            ->with('user')
            ->get()
            ->pluck('user');

        if ($users->isEmpty()) {
            return;
        }

        Notification::send(
            $users,
            new PriceDropNotification($event->variant, $event->oldAmount, $event->newAmount, $event->currency),
        );
    }
}
