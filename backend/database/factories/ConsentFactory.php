<?php

namespace Database\Factories;

use App\Enums\ConsentType;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consent>
 */
class ConsentFactory extends Factory
{
    protected $model = Consent::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'guest_identifier' => null,
            'type' => ConsentType::Marketing,
            'version' => null,
            'legal_document_id' => null,
            'accepted' => true,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'guest_identifier' => (string) fake()->uuid(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['accepted' => false]);
    }
}
