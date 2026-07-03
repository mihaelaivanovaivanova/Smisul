<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_customer_cannot_create_a_category(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->postJson('/api/v1/admin/categories', ['name' => 'Teas'])
            ->assertForbidden();
    }

    #[Test]
    public function an_administrator_can_create_a_category(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/categories', ['name' => 'Teas']);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'teas');
    }

    #[Test]
    public function a_category_cannot_be_made_its_own_parent(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/categories/{$category->id}", [
                'name' => $category->name,
                'parent_id' => $category->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    #[Test]
    public function an_administrator_can_deactivate_a_category(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = Category::factory()->create(['is_active' => true]);

        $this->actingAs($admin)->putJson("/api/v1/admin/categories/{$category->id}", [
            'name' => $category->name,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function an_administrator_can_delete_a_category(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/v1/admin/categories/{$category->id}")->assertNoContent();

        $this->assertSoftDeleted($category);
    }
}
