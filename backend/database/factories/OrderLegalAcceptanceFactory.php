<?php

namespace Database\Factories;

use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\OrderLegalAcceptance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLegalAcceptance>
 */
class OrderLegalAcceptanceFactory extends Factory
{
    protected $model = OrderLegalAcceptance::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'legal_document_id' => LegalDocument::factory(),
            'accepted_at' => now(),
        ];
    }
}
