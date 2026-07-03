<?php

namespace Tests\Unit\Models;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_hashes_the_password_automatically(): void
    {
        $user = User::factory()->create(['password' => 'plain-text-password']);

        $this->assertNotSame('plain-text-password', $user->password);
        $this->assertTrue(Hash::check('plain-text-password', $user->password));
    }

    #[Test]
    public function it_casts_role_to_the_role_enum(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(Role::class, $user->role);
        $this->assertSame(Role::Customer, $user->role);
    }

    #[Test]
    public function it_reports_administrator_and_customer_status_correctly(): void
    {
        $customer = User::factory()->create();
        $administrator = User::factory()->administrator()->create();

        $this->assertTrue($customer->isCustomer());
        $this->assertFalse($customer->isAdministrator());

        $this->assertTrue($administrator->isAdministrator());
        $this->assertFalse($administrator->isCustomer());
    }

    #[Test]
    public function it_builds_the_full_name(): void
    {
        $user = User::factory()->make(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $this->assertSame('Ada Lovelace', $user->fullName());
    }

    #[Test]
    public function it_never_exposes_password_or_remember_token_when_serialized(): void
    {
        $user = User::factory()->create();

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }
}
