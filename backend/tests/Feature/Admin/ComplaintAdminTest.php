<?php

namespace Tests\Feature\Admin;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplaintAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_access_the_complaints_register(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/complaints')->assertForbidden();
    }

    #[Test]
    public function a_guest_is_unauthenticated_on_complaint_endpoints(): void
    {
        $this->getJson('/api/v1/admin/complaints')->assertUnauthorized();
    }

    #[Test]
    public function an_administrator_can_log_a_complaint_against_an_order(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/complaints', [
            'order_number' => $order->order_number,
            'description' => 'Продуктът пристигна с повредена опаковка.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'received');
        $response->assertJsonPath('data.order.id', $order->id);
        $this->assertNotEmpty($response->json('data.complaint_number'));
    }

    #[Test]
    public function an_unknown_order_number_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/complaints', [
            'order_number' => 'DOES-NOT-EXIST',
            'description' => 'Some complaint.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order_number']);
    }

    #[Test]
    public function complaint_numbers_are_sequential_and_gap_free(): void
    {
        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create();

        $first = $this->actingAs($admin)->postJson('/api/v1/admin/complaints', [
            'order_number' => $order->order_number,
            'description' => 'First complaint.',
        ]);
        $second = $this->actingAs($admin)->postJson('/api/v1/admin/complaints', [
            'order_number' => $order->order_number,
            'description' => 'Second complaint.',
        ]);

        $firstNumber = (int) $first->json('data.complaint_number');
        $secondNumber = (int) $second->json('data.complaint_number');

        $this->assertSame($firstNumber + 1, $secondNumber);
    }

    #[Test]
    public function complaints_can_be_searched_by_complaint_number(): void
    {
        $admin = User::factory()->administrator()->create();
        $match = Complaint::factory()->create(['complaint_number' => '0000000042']);
        Complaint::factory()->create(['complaint_number' => '0000000099']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/complaints?search=0042');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $match->id);
    }

    #[Test]
    public function complaints_default_to_newest_submitted_first(): void
    {
        $admin = User::factory()->administrator()->create();
        $older = Complaint::factory()->create(['submitted_at' => now()->subDays(2)]);
        $newer = Complaint::factory()->create(['submitted_at' => now()]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/complaints');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newer->id);
        $response->assertJsonPath('data.1.id', $older->id);
    }

    #[Test]
    public function complaints_can_be_sorted_by_complaint_number_ascending(): void
    {
        $admin = User::factory()->administrator()->create();
        $high = Complaint::factory()->create(['complaint_number' => '0000000099']);
        $low = Complaint::factory()->create(['complaint_number' => '0000000042']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/complaints?sort=number_asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $low->id);
        $response->assertJsonPath('data.1.id', $high->id);
    }

    #[Test]
    public function complaints_can_be_sorted_by_status(): void
    {
        $admin = User::factory()->administrator()->create();
        $received = Complaint::factory()->create(['status' => ComplaintStatus::Received]);
        $resolved = Complaint::factory()->create(['status' => ComplaintStatus::Resolved]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/complaints?sort=status_asc');

        $response->assertOk();
        // Alphabetical: "in_review" < "received" < "rejected" < "resolved" -
        // with only these two present, "received" sorts before "resolved".
        $response->assertJsonPath('data.0.id', $received->id);
        $response->assertJsonPath('data.1.id', $resolved->id);
    }

    #[Test]
    public function an_administrator_can_list_and_filter_complaints_by_status(): void
    {
        $admin = User::factory()->administrator()->create();
        Complaint::factory()->create(['status' => ComplaintStatus::Received]);
        Complaint::factory()->create(['status' => ComplaintStatus::Resolved]);

        $all = $this->actingAs($admin)->getJson('/api/v1/admin/complaints');
        $all->assertOk();
        $all->assertJsonCount(2, 'data');

        $filtered = $this->actingAs($admin)->getJson('/api/v1/admin/complaints?status=resolved');
        $filtered->assertOk();
        $filtered->assertJsonCount(1, 'data');
        $filtered->assertJsonPath('data.0.status', 'resolved');
    }

    #[Test]
    public function an_administrator_can_resolve_a_complaint_and_resolved_at_is_stamped(): void
    {
        $admin = User::factory()->administrator()->create();
        $complaint = Complaint::factory()->create(['status' => ComplaintStatus::Received]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/complaints/{$complaint->id}", [
            'status' => 'resolved',
            'resolution' => 'Изпратен е нов продукт за замяна.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'resolved');
        $response->assertJsonPath('data.resolution', 'Изпратен е нов продукт за замяна.');
        $this->assertNotNull($response->json('data.resolved_at'));
    }

    #[Test]
    public function moving_to_in_review_does_not_stamp_resolved_at(): void
    {
        $admin = User::factory()->administrator()->create();
        $complaint = Complaint::factory()->create(['status' => ComplaintStatus::Received]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/complaints/{$complaint->id}", [
            'status' => 'in_review',
        ]);

        $response->assertOk();
        $this->assertNull($response->json('data.resolved_at'));
    }

    #[Test]
    public function there_is_no_way_to_delete_a_register_entry(): void
    {
        $admin = User::factory()->administrator()->create();
        $complaint = Complaint::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/v1/admin/complaints/{$complaint->id}")->assertStatus(405);
    }
}
