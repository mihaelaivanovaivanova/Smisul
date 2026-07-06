<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => PaymentProvider::ICard,
            'payment_method' => PaymentMethod::Card,
            'status' => PaymentStatus::Pending,
            'amount' => fake()->randomFloat(2, 10, 200),
            'currency' => 'EUR',
            'transaction_reference' => (string) Str::uuid(),
            'provider_reference' => null,
            'redirect_url' => null,
            'raw_response' => null,
            'initiated_at' => null,
            'completed_at' => null,
        ];
    }

    public function initiated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Initiated,
            'provider_reference' => (string) Str::uuid(),
            'redirect_url' => 'https://sandbox.icard.example/api/pay?reference=test',
            'initiated_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Paid,
            'provider_reference' => (string) Str::uuid(),
            'initiated_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    public function applePay(): static
    {
        return $this->state(fn (array $attributes) => ['payment_method' => PaymentMethod::ApplePay]);
    }

    public function googlePay(): static
    {
        return $this->state(fn (array $attributes) => ['payment_method' => PaymentMethod::GooglePay]);
    }
}
