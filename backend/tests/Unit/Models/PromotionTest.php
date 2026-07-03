<?php

namespace Tests\Unit\Models;

use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_active_promotion_with_no_date_bounds_is_valid(): void
    {
        $promotion = Promotion::factory()->make(['is_active' => true, 'starts_at' => null, 'ends_at' => null]);

        $this->assertTrue($promotion->isCurrentlyValid());
    }

    #[Test]
    public function an_inactive_promotion_is_never_valid(): void
    {
        $promotion = Promotion::factory()->inactive()->make();

        $this->assertFalse($promotion->isCurrentlyValid());
    }

    #[Test]
    public function a_promotion_before_its_start_date_is_not_valid(): void
    {
        $promotion = Promotion::factory()->make([
            'is_active' => true,
            'starts_at' => now()->addDay(),
            'ends_at' => null,
        ]);

        $this->assertFalse($promotion->isCurrentlyValid());
    }

    #[Test]
    public function an_expired_promotion_is_not_valid(): void
    {
        $promotion = Promotion::factory()->expired()->make();

        $this->assertFalse($promotion->isCurrentlyValid());
    }

    #[Test]
    public function a_promotion_that_reached_its_usage_limit_is_not_valid(): void
    {
        $promotion = Promotion::factory()->make([
            'is_active' => true,
            'usage_limit' => 5,
            'used_count' => 5,
        ]);

        $this->assertFalse($promotion->isCurrentlyValid());
    }

    #[Test]
    public function a_promotion_under_its_usage_limit_is_valid(): void
    {
        $promotion = Promotion::factory()->make([
            'is_active' => true,
            'usage_limit' => 5,
            'used_count' => 4,
        ]);

        $this->assertTrue($promotion->isCurrentlyValid());
    }
}
