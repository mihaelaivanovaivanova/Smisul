<?php

namespace Tests\Feature\Admin;

use App\Models\FunnelLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FunnelLeadAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_access_lead_endpoints(): void
    {
        $customer = User::factory()->create();
        $lead = FunnelLead::create(['email' => 'ada@example.com']);

        $this->actingAs($customer)->getJson('/api/v1/admin/funnel/leads')->assertForbidden();
        $this->actingAs($customer)->get('/api/v1/admin/funnel/leads/export')->assertForbidden();
        $this->actingAs($customer)->deleteJson("/api/v1/admin/funnel/leads/{$lead->id}")->assertForbidden();
    }

    #[Test]
    public function a_guest_is_unauthenticated_on_lead_endpoints(): void
    {
        $this->getJson('/api/v1/admin/funnel/leads')->assertUnauthorized();
    }

    #[Test]
    public function an_administrator_can_list_leads_newest_first(): void
    {
        $admin = User::factory()->administrator()->create();
        FunnelLead::create(['email' => 'older@example.com']);
        FunnelLead::create(['email' => 'newer@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/funnel/leads');

        $response->assertOk();
        $response->assertJsonPath('data.0.email', 'newer@example.com');
        $response->assertJsonPath('data.1.email', 'older@example.com');
        $response->assertJsonPath('meta.total', 2);
    }

    #[Test]
    public function an_administrator_can_search_leads_by_email_substring(): void
    {
        $admin = User::factory()->administrator()->create();
        FunnelLead::create(['email' => 'ada@example.com']);
        FunnelLead::create(['email' => 'grace@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/funnel/leads?email=ada');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.email', 'ada@example.com');
    }

    #[Test]
    public function an_administrator_can_export_leads_as_csv(): void
    {
        $admin = User::factory()->administrator()->create();
        FunnelLead::create(['email' => 'ada@example.com']);
        FunnelLead::create(['email' => 'grace@example.com']);

        $response = $this->actingAs($admin)->get('/api/v1/admin/funnel/leads/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('email,created_at', $csv);
        $this->assertStringContainsString('ada@example.com', $csv);
        $this->assertStringContainsString('grace@example.com', $csv);
    }

    #[Test]
    public function an_administrator_can_delete_a_lead(): void
    {
        $admin = User::factory()->administrator()->create();
        $lead = FunnelLead::create(['email' => 'ada@example.com']);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/funnel/leads/{$lead->id}")->assertNoContent();

        $this->assertDatabaseMissing('funnel_leads', ['email' => 'ada@example.com']);
    }
}
