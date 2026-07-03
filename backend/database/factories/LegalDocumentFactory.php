<?php

namespace Database\Factories;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalDocument>
 */
class LegalDocumentFactory extends Factory
{
    protected $model = LegalDocument::class;

    public function definition(): array
    {
        $type = fake()->randomElement(LegalDocumentType::cases());

        return [
            'type' => $type,
            'version' => '1.0',
            'title' => $type->label(),
            'content' => fake()->paragraphs(3, true),
            'is_current' => true,
            'published_at' => now(),
        ];
    }
}
