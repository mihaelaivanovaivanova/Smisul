<?php

namespace Tests\Feature\Favorites;

use App\DataTransferObjects\PriceData;
use App\Models\Favorite;
use App\Models\Inventory;
use App\Models\Price;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\BackInStockNotification;
use App\Notifications\PriceDropNotification;
use App\Services\InventoryService;
use App\Services\PriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FavoriteNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function favoriting_customers_are_emailed_when_a_variants_price_drops(): void
    {
        Notification::fake();

        $variant = ProductVariant::factory()->create();
        Price::factory()->for($variant, 'productVariant')->create(['amount' => 20]);
        $favoriter = User::factory()->create();
        $nonFavoriter = User::factory()->create();
        Favorite::factory()->for($favoriter)->for($variant, 'productVariant')->create();

        app(PriceService::class)->setPrice($variant, new PriceData(currency: 'EUR', amount: 15));

        Notification::assertSentTo($favoriter, function (PriceDropNotification $notification) {
            return $notification->oldAmount === 20.0 && $notification->newAmount === 15.0;
        });
        Notification::assertNotSentTo($nonFavoriter, PriceDropNotification::class);
    }

    #[Test]
    public function no_notification_is_sent_when_the_price_increases(): void
    {
        Notification::fake();

        $variant = ProductVariant::factory()->create();
        Price::factory()->for($variant, 'productVariant')->create(['amount' => 20]);
        $favoriter = User::factory()->create();
        Favorite::factory()->for($favoriter)->for($variant, 'productVariant')->create();

        app(PriceService::class)->setPrice($variant, new PriceData(currency: 'EUR', amount: 25));

        Notification::assertNothingSentTo($favoriter);
    }

    #[Test]
    public function no_notification_is_sent_when_nobody_favorited_the_variant(): void
    {
        Notification::fake();

        $variant = ProductVariant::factory()->create();
        Price::factory()->for($variant, 'productVariant')->create(['amount' => 20]);

        app(PriceService::class)->setPrice($variant, new PriceData(currency: 'EUR', amount: 10));

        Notification::assertNothingSent();
    }

    #[Test]
    public function favoriting_customers_are_emailed_when_a_variant_comes_back_in_stock(): void
    {
        Notification::fake();

        $variant = ProductVariant::factory()->create();
        $inventory = Inventory::factory()->for($variant, 'productVariant')->create(['quantity_on_hand' => 0]);
        $favoriter = User::factory()->create();
        Favorite::factory()->for($favoriter)->for($variant, 'productVariant')->create();

        app(InventoryService::class)->increaseStock($inventory, 10);

        Notification::assertSentTo($favoriter, BackInStockNotification::class);
    }

    #[Test]
    public function no_back_in_stock_notification_is_sent_when_already_in_stock(): void
    {
        Notification::fake();

        $variant = ProductVariant::factory()->create();
        $inventory = Inventory::factory()->for($variant, 'productVariant')->create(['quantity_on_hand' => 5]);
        $favoriter = User::factory()->create();
        Favorite::factory()->for($favoriter)->for($variant, 'productVariant')->create();

        app(InventoryService::class)->increaseStock($inventory, 10);

        Notification::assertNothingSentTo($favoriter);
    }

    #[Test]
    public function setting_the_admin_quantity_field_also_triggers_a_back_in_stock_notification(): void
    {
        Notification::fake();

        $variant = ProductVariant::factory()->create();
        $inventory = Inventory::factory()->for($variant, 'productVariant')->create(['quantity_on_hand' => 0]);
        $favoriter = User::factory()->create();
        Favorite::factory()->for($favoriter)->for($variant, 'productVariant')->create();

        app(InventoryService::class)->setQuantityOnHand($inventory, 20);

        Notification::assertSentTo($favoriter, BackInStockNotification::class);
    }
}
