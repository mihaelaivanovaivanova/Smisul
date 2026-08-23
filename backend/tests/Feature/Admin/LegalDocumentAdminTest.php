<?php

namespace Tests\Feature\Admin;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegalDocumentAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_list_legal_documents(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/legal-documents')->assertForbidden();
    }

    #[Test]
    public function a_guest_is_unauthenticated_on_legal_document_endpoints(): void
    {
        $this->getJson('/api/v1/admin/legal-documents')->assertUnauthorized();
    }

    #[Test]
    public function an_administrator_can_list_every_version(): void
    {
        $admin = User::factory()->administrator()->create();
        LegalDocument::factory()->create(['type' => LegalDocumentType::TermsOfService]);
        LegalDocument::factory()->create(['type' => LegalDocumentType::PrivacyPolicy]);
        LegalDocument::factory()->create(['type' => LegalDocumentType::ShippingPolicy]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/legal-documents');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    #[Test]
    public function an_administrator_can_publish_a_new_version_which_supersedes_the_current_one(): void
    {
        $admin = User::factory()->administrator()->create();
        $current = LegalDocument::factory()->create([
            'type' => LegalDocumentType::TermsOfService,
            'version' => '1.0',
            'is_current' => true,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/legal-documents', [
            'type' => 'terms_of_service',
            'version' => '2.0',
            'title' => 'General Terms v2',
            'content' => 'Updated terms.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.version', '2.0');
        $response->assertJsonPath('data.is_current', true);
        $this->assertFalse($current->fresh()->is_current);
    }

    #[Test]
    public function publishing_a_duplicate_version_for_the_same_type_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();
        LegalDocument::factory()->create([
            'type' => LegalDocumentType::PrivacyPolicy,
            'version' => '1.0',
        ]);

        $this->actingAs($admin)->postJson('/api/v1/admin/legal-documents', [
            'type' => 'privacy_policy',
            'version' => '1.0',
            'title' => 'Privacy Policy',
        ])->assertUnprocessable();
    }
}
