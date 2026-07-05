<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function homepage_content_is_publicly_readable(): void
    {
        ContentBlock::query()->create(['key' => 'homepage.hero', 'content' => ['eyebrow' => 'E', 'title' => 'T', 'subtitle' => 'S', 'cta' => 'C']]);

        $response = $this->getJson('/api/v1/content/homepage');

        $response->assertOk();
        $response->assertJsonPath('data.hero.title', 'T');
    }

    #[Test]
    public function a_missing_section_defaults_to_an_empty_object_instead_of_erroring(): void
    {
        $response = $this->getJson('/api/v1/content/homepage');

        $response->assertOk();
        $response->assertJsonPath('data.faq', []);
    }
}
