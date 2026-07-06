<?php

namespace Tests\Unit\Services;

use App\Enums\ConsentType;
use App\Enums\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_registers_a_new_customer_with_the_default_role(): void
    {
        Event::fake();

        $user = $this->app->make(AuthService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'password' => 'Sup3rSecret1',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
        $this->assertSame(Role::Customer, $user->fresh()->role);
        $this->assertNotNull($user->gdpr_consent_at);

        $this->assertDatabaseHas('consents', ['user_id' => $user->id, 'type' => ConsentType::Terms->value, 'accepted' => true]);
        $this->assertDatabaseHas('consents', ['user_id' => $user->id, 'type' => ConsentType::Privacy->value, 'accepted' => true]);
        $this->assertDatabaseHas('consents', ['user_id' => $user->id, 'type' => ConsentType::Marketing->value, 'accepted' => false]);
        $this->assertDatabaseHas('consents', ['user_id' => $user->id, 'type' => ConsentType::Newsletter->value, 'accepted' => false]);

        Event::assertDispatched(Registered::class);
    }

    #[Test]
    public function register_ignores_any_client_supplied_role(): void
    {
        $user = $this->app->make(AuthService::class)->register([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'password' => 'Sup3rSecret1',
            'role' => 'administrator',
        ]);

        $this->assertSame(Role::Customer, $user->fresh()->role);
    }

    #[Test]
    public function it_throws_a_validation_exception_on_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'correct-password']);

        $this->expectException(ValidationException::class);

        $this->app->make(AuthService::class)->login($this->requestWithSession(), 'ada@example.com', 'wrong-password', false);
    }

    #[Test]
    public function it_rejects_login_when_the_request_has_no_session(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'correct-password']);

        try {
            $this->app->make(AuthService::class)->login(Request::create('/'), 'ada@example.com', 'correct-password', false);
            $this->fail('Expected an HttpException to be thrown.');
        } catch (HttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
        }
    }

    private function requestWithSession(): Request
    {
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session']->driver());

        return $request;
    }
}
