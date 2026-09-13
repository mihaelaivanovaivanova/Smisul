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

    #[Test]
    public function a_category_with_no_seo_row_yet_returns_null_seo(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->getJson("/api/v1/admin/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.seo', null);
    }

    #[Test]
    public function an_administrator_can_set_a_categorys_seo_fields(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/categories/{$category->id}", [
            'name' => $category->name,
            'seo' => [
                'meta_title' => 'Билки и чайове | Smisul',
                'meta_description' => 'Разгледай нашата селекция от билки и чайове.',
                'og_image_path' => 'https://smisul.bg/categories/teas.jpg',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.seo.meta_title', 'Билки и чайове | Smisul');
        $response->assertJsonPath('data.seo.og_image_url', 'https://smisul.bg/categories/teas.jpg');
        $this->assertDatabaseHas('seo', [
            'seoable_type' => Category::class,
            'seoable_id' => $category->id,
            'meta_title' => 'Билки и чайове | Smisul',
        ]);
    }

    #[Test]
    public function updating_a_category_without_a_seo_key_leaves_its_existing_seo_data_untouched(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = Category::factory()->create();
        $category->seo()->create(['meta_title' => 'Original title']);

        $this->actingAs($admin)->putJson("/api/v1/admin/categories/{$category->id}", [
            'name' => 'Renamed',
        ])->assertOk();

        $this->assertDatabaseHas('seo', [
            'seoable_type' => Category::class,
            'seoable_id' => $category->id,
            'meta_title' => 'Original title',
        ]);
    }
}
