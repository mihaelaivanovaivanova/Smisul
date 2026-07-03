<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'guest_token' => (string) Str::uuid(),
            'currency' => Currency::EUR->value,
        ];
    }

    public function forUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => ($user ?? User::factory()->create())->id,
            'guest_token' => null,
        ]);
    }
}
