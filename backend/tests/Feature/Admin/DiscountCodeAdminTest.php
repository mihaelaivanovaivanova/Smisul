<?php

namespace Tests\Feature\Admin;

use App\Models\DiscountCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscountCodeAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_administrator_can_list_discount_codes(): void
    {
        $admin = User::factory()->administrator()->create();
        DiscountCode::create(['code' => 'ONE', 'discount_percent' => 10, 'max_uses' => 1]);
        DiscountCode::create(['code' => 'TWO', 'discount_percent' => 20, 'max_uses' => 1]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/discount-codes');

        $response->assertOk();
        // Not an exact count - a migration-seeded promo code (SPOOKY10)
        // already lives in the table alongside whatever this test creates.
        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('ONE'));
        $this->assertTrue($codes->contains('TWO'));
    }

    #[Test]
    public function a_customer_cannot_list_discount_codes(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->getJson('/api/v1/admin/discount-codes')->assertForbidden();
    }

    /**
     * The plain date (Y-m-d) the admin form sends is interpreted as a full
     * day in the store's own local time (Europe/Sofia), not the app's UTC
     * - see DiscountCodeData::dayBoundary()'s own docblock for why.
     */
    #[Test]
    public function an_administrator_can_create_a_discount_code_with_a_local_date_window(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/discount-codes', [
            'code' => 'fall-sale',
            'discount_percent' => 10,
            'max_uses' => 500,
            'starts_at' => '2026-10-31',
            'expires_at' => '2026-10-31',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'FALL-SALE');
        // 10, not 10.0 - json_encode() drops the fractional part for a
        // whole-number float by default, so the response really does read
        // 10 (matching the existing convention elsewhere, e.g.
        // DiscountCodeCheckoutTest's own discount_percent assertions).
        $response->assertJsonPath('data.discount_percent', 10);
        $response->assertJsonPath('data.max_uses', 500);
        $response->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('discount_codes', [
            'code' => 'FALL-SALE',
            'starts_at' => '2026-10-30 22:00:00',
            'expires_at' => '2026-10-31 21:59:59',
        ]);
        $this->assertDatabaseHas('admin_action_logs', [
            'user_id' => $admin->id,
            'action' => 'discount_code.created',
        ]);
    }

    #[Test]
    public function creating_a_discount_code_rejects_a_duplicate_code_case_insensitively(): void
    {
        $admin = User::factory()->administrator()->create();
        DiscountCode::create(['code' => 'EXISTING-CODE', 'discount_percent' => 10, 'max_uses' => 1]);

        $this->actingAs($admin)->postJson('/api/v1/admin/discount-codes', [
            'code' => 'existing-code',
            'discount_percent' => 15,
            'max_uses' => 100,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    #[Test]
    public function creating_a_discount_code_rejects_a_percentage_over_100(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/discount-codes', [
            'code' => 'TOOMUCH',
            'discount_percent' => 150,
            'max_uses' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('discount_percent');
    }

    #[Test]
    public function creating_a_discount_code_rejects_an_end_date_before_the_start_date(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/discount-codes', [
            'code' => 'BACKWARDS',
            'discount_percent' => 10,
            'max_uses' => 1,
            'starts_at' => '2026-11-05',
            'expires_at' => '2026-11-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('expires_at');
    }

    #[Test]
    public function a_customer_cannot_create_a_discount_code(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->postJson('/api/v1/admin/discount-codes', [
            'code' => 'NOPE',
            'discount_percent' => 10,
            'max_uses' => 1,
        ])->assertForbidden();
    }

    #[Test]
    public function an_administrator_can_update_a_discount_code(): void
    {
        $admin = User::factory()->administrator()->create();
        $discountCode = DiscountCode::create(['code' => 'OLDNAME', 'discount_percent' => 10, 'max_uses' => 10]);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/discount-codes/{$discountCode->id}", [
            'code' => 'NEWNAME',
            'discount_percent' => 25,
            'max_uses' => 50,
            'is_active' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.code', 'NEWNAME');
        $response->assertJsonPath('data.discount_percent', 25);
        $response->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('admin_action_logs', [
            'user_id' => $admin->id,
            'action' => 'discount_code.updated',
        ]);
    }

    #[Test]
    public function updating_a_discount_code_rejects_max_uses_below_its_already_used_count(): void
    {
        $admin = User::factory()->administrator()->create();
        $discountCode = DiscountCode::create(['code' => 'USED', 'discount_percent' => 10, 'max_uses' => 10, 'used_count' => 7]);

        $this->actingAs($admin)->putJson("/api/v1/admin/discount-codes/{$discountCode->id}", [
            'code' => 'USED',
            'discount_percent' => 10,
            'max_uses' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors('max_uses');
    }

    #[Test]
    public function updating_a_discount_code_allows_keeping_its_own_code_unchanged(): void
    {
        $admin = User::factory()->administrator()->create();
        $discountCode = DiscountCode::create(['code' => 'SAME', 'discount_percent' => 10, 'max_uses' => 10]);

        $this->actingAs($admin)->putJson("/api/v1/admin/discount-codes/{$discountCode->id}", [
            'code' => 'SAME',
            'discount_percent' => 20,
            'max_uses' => 10,
        ])->assertOk();
    }

    #[Test]
    public function a_customer_cannot_update_a_discount_code(): void
    {
        $customer = User::factory()->create();
        $discountCode = DiscountCode::create(['code' => 'LOCKED', 'discount_percent' => 10, 'max_uses' => 1]);

        $this->actingAs($customer)->putJson("/api/v1/admin/discount-codes/{$discountCode->id}", [
            'code' => 'LOCKED',
            'discount_percent' => 50,
            'max_uses' => 1,
        ])->assertForbidden();
    }

    #[Test]
    public function an_administrator_can_delete_a_discount_code(): void
    {
        $admin = User::factory()->administrator()->create();
        $discountCode = DiscountCode::create(['code' => 'DELETE-ME', 'discount_percent' => 10, 'max_uses' => 1]);

        $this->actingAs($admin)->deleteJson("/api/v1/admin/discount-codes/{$discountCode->id}")->assertNoContent();

        $this->assertDatabaseMissing('discount_codes', ['id' => $discountCode->id]);
        $this->assertDatabaseHas('admin_action_logs', [
            'user_id' => $admin->id,
            'action' => 'discount_code.deleted',
        ]);
    }

    #[Test]
    public function a_customer_cannot_delete_a_discount_code(): void
    {
        $customer = User::factory()->create();
        $discountCode = DiscountCode::create(['code' => 'KEEP-ME', 'discount_percent' => 10, 'max_uses' => 1]);

        $this->actingAs($customer)->deleteJson("/api/v1/admin/discount-codes/{$discountCode->id}")->assertForbidden();

        $this->assertDatabaseHas('discount_codes', ['id' => $discountCode->id]);
    }
}
