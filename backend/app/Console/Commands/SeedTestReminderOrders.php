<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Services\OrderNumberGenerator;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * On-demand tooling for live-testing the 30-day reminder email (and the
 * review-reward code it leads to) against a real deployed environment -
 * never scheduled, never run automatically on deploy, only invoked by hand
 * via SSH when someone actually wants fresh orders to click "Send test
 * reminder email" on. Mirrors the TEMPORARY test-reminder route this app
 * already carried for the same purpose, as a safer non-route alternative.
 *
 * Every order uses one of OrderService::TEST_CUSTOMER_EMAILS - the same
 * whitelist Admin\OrderController::sendTestReminderEmail() itself checks
 * (via OrderResource::is_test_account) before it'll let the admin "Send
 * test reminder email" button appear/fire at all, so every order this
 * creates is guaranteed usable with that button immediately, with no
 * further setup. By default cycles through all of them; pass --email to
 * pin every order to one specific whitelisted address instead (e.g. to
 * inspect a batch of real sends landing in one real inbox).
 */
class SeedTestReminderOrders extends Command
{
    protected $signature = 'orders:seed-test-reminders {count=10 : How many test orders to create} {--email= : Use this one whitelisted test-account email for every order instead of cycling through all of them}';

    protected $description = 'Create Delivered test orders (one of the whitelisted test accounts each) ready for the admin "Send test reminder email" button';

    /** @var list<array{first: string, last: string}> */
    private const CUSTOMER_NAMES = [
        ['first' => 'Иван', 'last' => 'Петров'],
        ['first' => 'Мария', 'last' => 'Георгиева'],
        ['first' => 'Георги', 'last' => 'Димитров'],
        ['first' => 'Елена', 'last' => 'Стоянова'],
        ['first' => 'Николай', 'last' => 'Василев'],
        ['first' => 'Виктория', 'last' => 'Тодорова'],
        ['first' => 'Димитър', 'last' => 'Христов'],
        ['first' => 'Радост', 'last' => 'Иванова'],
        ['first' => 'Стоян', 'last' => 'Николов'],
        ['first' => 'Симона', 'last' => 'Ангелова'],
    ];

    public function handle(OrderNumberGenerator $orderNumbers): int
    {
        $count = (int) $this->argument('count');

        $variants = $this->purchasableVariants();

        if ($variants->isEmpty()) {
            $this->error('No purchasable product variants with a EUR price found - nothing to put on these test orders.');

            return self::FAILURE;
        }

        $requestedEmail = $this->option('email');

        if ($requestedEmail !== null && ! OrderService::isTestCustomerEmail($requestedEmail)) {
            $this->error("'{$requestedEmail}' is not one of the whitelisted test accounts (see OrderService::TEST_CUSTOMER_EMAILS) - the \"Send test reminder email\" button would never appear for these orders.");

            return self::FAILURE;
        }

        $emails = $requestedEmail !== null ? [$requestedEmail] : OrderService::TEST_CUSTOMER_EMAILS;

        for ($i = 0; $i < $count; $i++) {
            $name = self::CUSTOMER_NAMES[$i % count(self::CUSTOMER_NAMES)];
            $email = $emails[$i % count($emails)];
            $variant = $variants[$i % $variants->count()];
            $price = (float) $variant->prices->first()->amount;

            $order = Order::create([
                'order_number' => $orderNumbers->generate(),
                'status' => OrderStatus::Delivered,
                'currency' => 'EUR',
                'customer_first_name' => $name['first'],
                'customer_last_name' => $name['last'],
                'customer_email' => $email,
                'customer_phone' => '+359888000000',
                'shipping_country' => 'Bulgaria',
                'shipping_city' => 'Sofia',
                'shipping_postal_code' => '1000',
                'shipping_address_line' => 'ул. Тестова 1',
                'shipping_carrier' => 'speedy',
                'shipping_delivery_type' => 'address',
                'shipping_method_label' => 'Speedy - до адрес',
                'shipping_price' => 5.99,
                'subtotal' => $price,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => round($price + 5.99, 2),
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'quantity' => 1,
                'unit_price' => $price,
                'line_total' => $price,
            ]);

            $this->info("#{$order->id} {$order->order_number} - {$name['first']} {$name['last']} <{$email}> - {$variant->product->name}");
        }

        $this->info("Created {$count} test order(s). Open each one in /admin/orders/{id} and click \"Send test reminder email\".");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    private function purchasableVariants()
    {
        return ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->whereIn('slug', ['miswak', 'bambukov-keis-za-miswak', 'stargalka-za-ezik']))
            ->whereHas('prices', fn ($query) => $query->where('currency', 'EUR'))
            ->with(['product', 'prices'])
            ->get()
            ->filter(fn (ProductVariant $variant) => $variant->prices->isNotEmpty())
            ->values();
    }
}
