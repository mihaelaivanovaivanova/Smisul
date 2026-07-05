<?php

namespace App\Listeners;

use App\Events\Favorite\ProductBackInStock;
use App\Models\Favorite;
use App\Notifications\BackInStockNotification;
use Illuminate\Support\Facades\Notification;

class NotifyFavoritesOfBackInStock
{
    public function handle(ProductBackInStock $event): void
    {
        $users = Favorite::query()
            ->where('product_variant_id', $event->variant->id)
            ->with('user')
            ->get()
            ->pluck('user');

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new BackInStockNotification($event->variant));
    }
}
