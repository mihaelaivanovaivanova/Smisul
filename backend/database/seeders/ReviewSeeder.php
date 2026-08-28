<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Enums\ShippingCarrier;
use App\Enums\ShippingDeliveryType;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo customer reviews for the funnel product (Miswak) - the funnel
 * landing page's social-proof blocks (hero star rating + testimonials)
 * read from the real reviews API, so a fresh install needs real approved
 * rows behind them, in production as well as local dev - see
 * deployment/public_html/install.php's seeder list. Every author is a
 * demo account on @example.com and each review hangs off its own
 * delivered demo order, satisfying the schema's one-review-per-product-
 * per-order constraint.
 *
 * Builds the backing User/Order rows directly (no ::factory()) rather
 * than the more typical factory-based approach: fakerphp/faker is a
 * require-dev-only package, and the production installer runs
 * `composer install --no-dev` - a factory-based seeder would fatal with
 * a missing Faker\Generator class the moment it ran there. Every field
 * value below is fixed/deterministic instead of random for the same
 * reason (no fake() calls anywhere in this class).
 *
 * Idempotent: authors are matched by email and reviews by (user, product)
 * - re-running updates the text/rating in place instead of duplicating.
 * The backing demo order is only created alongside a review's first
 * insert; content edits never mint new orders.
 */
class ReviewSeeder extends Seeder
{
    /**
     * @var list<array{first_name: string, last_name: string, email: string, rating: int, title: string, body: string, helpful_count: int, days_ago: int}>
     */
    private const REVIEWS = [
        [
            'first_name' => 'Мария',
            'last_name' => 'Иванова',
            'email' => 'maria.demo@example.com',
            'rating' => 5,
            'title' => 'Чудесен заместител през деня',
            'body' => 'Взех си го от любопитство и в крайна сметка си го използвам в ежедневието. Не съм спряла да си мия зъбите с четка сутрин и вечер, но през деня мисвакът е супер удобен, когато съм навън.',
            'helpful_count' => 12,
            'days_ago' => 9,
        ],
        [
            'first_name' => 'Георги',
            'last_name' => 'Петров',
            'email' => 'georgi.demo@example.com',
            'rating' => 5,
            'title' => 'Удобен за пътуване',
            'body' => 'Самото подготвяне на върха отнема малко време първия път, но после е лесно. Държа един вкъщи и един в колата.',
            'helpful_count' => 9,
            'days_ago' => 16,
        ],
        [
            'first_name' => 'Елена',
            'last_name' => 'Стоянова',
            'email' => 'elena.demo@example.com',
            'rating' => 4,
            'title' => 'Хубаво усещане, малка забележка',
            'body' => 'Малко е неудобен за достигане на крайните зъби и вътрешната страна на предните. Иначе оставя усещане за чистота и полиране.',
            'helpful_count' => 7,
            'days_ago' => 24,
        ],
        [
            'first_name' => 'Димитър',
            'last_name' => 'Колев',
            'email' => 'dimitar.demo@example.com',
            'rating' => 5,
            'title' => 'Децата също го харесаха',
            'body' => 'Взехме семейния пакет и се оказа страхотно решение - на децата им е забавно, а аз съм спокоен, че няма химия. Сега на игра си чистят постоянно зъбите :) Излиза и по-изгодно на бройка. Ще поръчаме пак.',
            'helpful_count' => 5,
            'days_ago' => 31,
        ],
        [
            'first_name' => 'Виктория',
            'last_name' => 'Тодорова',
            'email' => 'viktoria.demo@example.com',
            'rating' => 4,
            'title' => 'По-малко плака между миенията',
            'body' => 'След около две седмици ми направи впечатление, че по зъбите ми се натрупва по-малко плака между миенията. Не бих казала, че избелва като професионална процедура, но визуално зъбите ми изглеждат по-чисти.',
            'helpful_count' => 8,
            'days_ago' => 42,
        ],
        [
            'first_name' => 'Стоян',
            'last_name' => 'Маринов',
            'email' => 'stoyan.demo@example.com',
            'rating' => 5,
            'title' => 'Работи по-добре от очакваното',
            'body' => 'Клонче вместо четка звучи като маркетинг, но наистина върши работа. След две седмици обаче зъбите ми са видимо по-чисти сутрин, а венците спряха да кървят при почистване. Убеди ме.',
            'helpful_count' => 4,
            'days_ago' => 55,
        ],
        [
            'first_name' => 'Иван',
            'last_name' => 'Николов',
            'email' => 'ivan.demo@example.com',
            'rating' => 5,
            'title' => 'Свикнах бързо и ми хареса',
            'body' => 'Честно казано ми беше странно първите 2–3 пъти. След като свикнах как се използва, започна много да ми харесва усещането след почистване. Най-често го нося в чантата и го ползвам след обяд.',
            'helpful_count' => 6,
            'days_ago' => 3,
        ],
        [
            'first_name' => 'Радост',
            'last_name' => 'Христова',
            'email' => 'radost.demo@example.com',
            'rating' => 5,
            'title' => 'Усещане за истинска чистота',
            'body' => 'Зъбите ми определено се усещат много чисти след почистване. Най ми харесва, че е изцяло естествен продукт.',
            'helpful_count' => 3,
            'days_ago' => 12,
        ],
        [
            'first_name' => 'Симона',
            'last_name' => 'Ангелова',
            'email' => 'simona.demo@example.com',
            'rating' => 4,
            'title' => 'Вкусът расте с времето',
            'body' => 'Първия път вкусът ми се стори неприятен, но след няколко дни вече не ми правеше впечатление и даже ми хареса :D. За мен най-големият плюс е, че мога да го използвам по всяко време.',
            'helpful_count' => 5,
            'days_ago' => 20,
        ],
        [
            'first_name' => 'Николай',
            'last_name' => 'Върбанов',
            'email' => 'nikolay.demo@example.com',
            'rating' => 5,
            'title' => 'Просто и работещо решение',
            'body' => 'Супер е колко е просто. Зарибих даже и приятеля ми, а той е доста скептичен с подобни продукти :D.',
            'helpful_count' => 7,
            'days_ago' => 48,
        ],
    ];

    public function run(): void
    {
        $product = Product::query()->firstWhere('slug', 'miswak');

        if ($product === null) {
            return;
        }

        $defaultVariant = $product->variants()->firstWhere('is_default', true);

        foreach (self::REVIEWS as $entry) {
            $user = User::query()->firstWhere('email', $entry['email']) ?? $this->createDemoUser($entry);

            $review = Review::query()
                ->where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->first();

            if ($review === null) {
                $order = $this->createDemoOrder($user);

                $review = (new Review)->forceFill([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                ]);
            }

            $review->forceFill([
                'product_variant_id' => $defaultVariant?->id,
                'rating' => $entry['rating'],
                'title' => $entry['title'],
                'body' => $entry['body'],
                'status' => ReviewStatus::Approved,
                'verified_purchase' => true,
                'helpful_count' => $entry['helpful_count'],
                'created_at' => now()->subDays($entry['days_ago']),
            ])->save();
        }
    }

    /**
     * @param  array{first_name: string, last_name: string, email: string}  $entry
     */
    private function createDemoUser(array $entry): User
    {
        $user = User::create([
            'first_name' => $entry['first_name'],
            'last_name' => $entry['last_name'],
            'email' => $entry['email'],
            'phone' => null,
            // Never meant to be logged into - random and discarded, not a
            // shared/guessable default.
            'password' => Str::random(32),
            'newsletter_subscription' => false,
            'marketing_consent' => false,
            'gdpr_consent_at' => now(),
        ]);

        $user->forceFill([
            'role' => Role::Customer,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    private function createDemoOrder(User $user): Order
    {
        // A single fixed demo shipping address - never shown to a real
        // customer, only used to satisfy the order's own NOT NULL
        // columns and the review's one-review-per-order constraint.
        return Order::create([
            'order_number' => 'DEMO-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'user_id' => $user->id,
            'guest_access_token' => null,
            'status' => OrderStatus::Delivered,
            'currency' => Currency::EUR->value,
            'customer_first_name' => $user->first_name,
            'customer_last_name' => $user->last_name,
            'customer_email' => $user->email,
            'customer_phone' => '+359000000000',
            'shipping_country' => 'Bulgaria',
            'shipping_city' => 'София',
            'shipping_postal_code' => '1000',
            'shipping_address_line' => 'demo',
            'billing_same_as_shipping' => true,
            'billing_country' => 'Bulgaria',
            'billing_city' => 'София',
            'billing_postal_code' => '1000',
            'billing_address_line' => 'demo',
            'shipping_carrier' => ShippingCarrier::BoxNow,
            'shipping_delivery_type' => ShippingDeliveryType::Locker,
            'shipping_method_label' => ShippingCarrier::BoxNow->label(),
            'shipping_price' => 0,
            'subtotal' => 17.49,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 17.49,
        ]);
    }
}
