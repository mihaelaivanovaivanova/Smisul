<?php

namespace Database\Factories;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        return [
            'complaint_number' => str_pad((string) fake()->unique()->numberBetween(1, 999999999), 10, '0', STR_PAD_LEFT),
            'order_id' => Order::factory(),
            'description' => fake()->sentence(12),
            'status' => ComplaintStatus::Received,
            'resolution' => null,
            'submitted_at' => now(),
            'resolved_at' => null,
        ];
    }
}
