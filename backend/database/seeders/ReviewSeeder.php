<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo customer reviews for the funnel product (Miswak) — the funnel
 * landing page's social-proof blocks (hero star rating + testimonials)
 * read from the real reviews API, so development needs real approved
 * rows behind them. Every author is a demo account on @example.com and
 * each review hangs off its own delivered demo order, satisfying the
 * schema's one-review-per-product-per-order constraint.
 *
 * Idempotent: authors are matched by email and reviews by (user, product)
 * — re-running updates the text/rating in place instead of duplicating.
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
            'title' => 'Изненадващо приятно усещане',
            'body' => 'Пробвах Miswak от любопитство и не очаквах да ми хареса толкова. Зъбите ми са гладки като след почистване при дентист, а дъхът остава свеж дълго време. Вече е винаги в чантата ми.',
            'helpful_count' => 12,
            'days_ago' => 9,
        ],
        [
            'first_name' => 'Георги',
            'last_name' => 'Петров',
            'email' => 'georgi.demo@example.com',
            'rating' => 5,
            'title' => 'Взимам го навсякъде',
            'body' => 'Пътувам почти всяка седмица и това, че не ми трябват нито паста, нито вода, е огромно предимство. Ползвам го в колата, в офиса, дори в самолета. Практично и никак не личи, че е „четка за зъби“.',
            'helpful_count' => 9,
            'days_ago' => 16,
        ],
        [
            'first_name' => 'Елена',
            'last_name' => 'Стоянова',
            'email' => 'elena.demo@example.com',
            'rating' => 4,
            'title' => 'Добра алтернатива',
            'body' => 'Отне ми няколко дни да свикна с вкуса — леко горчив в началото, после става приятен. Сега го предпочитам пред обикновената четка, когато съм навън. Единствено ми се иска дръжката да беше малко по-дълга.',
            'helpful_count' => 7,
            'days_ago' => 24,
        ],
        [
            'first_name' => 'Димитър',
            'last_name' => 'Колев',
            'email' => 'dimitar.demo@example.com',
            'rating' => 5,
            'title' => 'Децата също го харесаха',
            'body' => 'Взехме семейния пакет и се оказа страхотно решение — на децата им е забавно, а аз съм спокоен, че няма химия. Излиза и по-изгодно на бройка. Ще поръчаме пак.',
            'helpful_count' => 5,
            'days_ago' => 31,
        ],
        [
            'first_name' => 'Виктория',
            'last_name' => 'Тодорова',
            'email' => 'viktoria.demo@example.com',
            'rating' => 5,
            'title' => 'Нула пластмаса, нула отпадък',
            'body' => 'Търсех начин да махна пластмасата от банята и Miswak се оказа най-лесната първа стъпка. Изхабеният връх просто се отрязва — нищо не се изхвърля в кофата. Усещането за чистота е истинско, не е компромис.',
            'helpful_count' => 8,
            'days_ago' => 42,
        ],
        [
            'first_name' => 'Стоян',
            'last_name' => 'Маринов',
            'email' => 'stoyan.demo@example.com',
            'rating' => 5,
            'title' => 'Работи по-добре от очакваното',
            'body' => 'Признавам, че бях скептичен — клонче вместо четка звучи като маркетинг. След две седмици обаче зъбите ми са видимо по-чисти сутрин, а венците спряха да кървят при почистване. Убеди ме.',
            'helpful_count' => 4,
            'days_ago' => 55,
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
            $user = User::query()->firstWhere('email', $entry['email'])
                ?? User::factory()->create([
                    'first_name' => $entry['first_name'],
                    'last_name' => $entry['last_name'],
                    'email' => $entry['email'],
                ]);

            $review = Review::query()
                ->where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->first();

            if ($review === null) {
                $order = Order::factory()->forUser($user)->create([
                    'status' => OrderStatus::Delivered,
                    'customer_first_name' => $user->first_name,
                    'customer_last_name' => $user->last_name,
                    'customer_email' => $user->email,
                ]);

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
}
