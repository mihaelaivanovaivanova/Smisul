<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_can_view_and_update_their_own_profile(): void
    {
        $user = User::factory()->create();
        $policy = new UserPolicy;

        $this->assertTrue($policy->view($user, $user));
        $this->assertTrue($policy->update($user, $user));
    }

    #[Test]
    public function a_user_cannot_view_or_update_another_users_profile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $policy = new UserPolicy;

        $this->assertFalse($policy->view($user, $otherUser));
        $this->assertFalse($policy->update($user, $otherUser));
    }
}
