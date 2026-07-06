<?php

namespace Tests\Feature;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_every_current_legal_document(): void
    {
        LegalDocument::factory()->create(['type' => LegalDocumentType::TermsOfService, 'is_current' => true]);
        LegalDocument::factory()->create(['type' => LegalDocumentType::PrivacyPolicy, 'is_current' => true]);
        LegalDocument::factory()->create(['type' => LegalDocumentType::ShippingPolicy, 'is_current' => false]);

        $response = $this->getJson('/api/v1/legal-documents');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_shows_a_single_document_by_slug(): void
    {
        LegalDocument::factory()->create([
            'type' => LegalDocumentType::CookiePolicy,
            'title' => 'Cookie Policy',
            'is_current' => true,
        ]);

        $response = $this->getJson('/api/v1/legal-documents/cookie-policy');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'cookie-policy');
        $response->assertJsonPath('data.title', 'Cookie Policy');
    }

    #[Test]
    public function an_unknown_slug_returns_404(): void
    {
        $response = $this->getJson('/api/v1/legal-documents/not-a-real-document');

        $response->assertNotFound();
    }

    #[Test]
    public function a_known_type_with_no_published_document_returns_404(): void
    {
        LegalDocument::factory()->create(['type' => LegalDocumentType::ReturnsPolicy, 'is_current' => false]);

        $response = $this->getJson('/api/v1/legal-documents/returns-policy');

        $response->assertNotFound();
    }

    #[Test]
    public function only_the_current_version_of_a_type_is_returned(): void
    {
        LegalDocument::factory()->create([
            'type' => LegalDocumentType::TermsOfService,
            'version' => '1.0',
            'is_current' => false,
        ]);
        LegalDocument::factory()->create([
            'type' => LegalDocumentType::TermsOfService,
            'version' => '2.0',
            'is_current' => true,
        ]);

        $response = $this->getJson('/api/v1/legal-documents/terms-of-service');

        $response->assertOk();
        $response->assertJsonPath('data.version', '2.0');
    }
}
