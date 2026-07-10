<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Simulate every test request coming from the SPA's origin.
     *
     * Sanctum only attaches session/cookie handling (EnsureFrontendRequestsAreStateful)
     * to requests whose Referer/Origin header matches a configured stateful
     * domain — exactly like a real browser request from the React app would.
     * Without this, $request->session() is never available in tests, even
     * though it works correctly for real frontend traffic.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', config('app.frontend_url'));

        // ICardPaymentGateway::createModalSession() makes a real outbound
        // HTTP call for every Card payment, including the many tests that
        // place an order without caring about payment internals at all —
        // a harmless default fake keeps those passing. Only answers
        // IPGPaymentToken (the Card modal flow) and returns null for
        // everything else (wallet calls), so a payment-specific test's own
        // Http::fake([...]) can still catch those — Http::fake() stubs are
        // matched in registration order (first match wins), so an
        // unconditional stub registered here would always win over one a
        // test registers afterwards; returning null lets it fall through.
        Http::fake([
            rtrim((string) config('services.icard.base_url'), '/').'/*' => function ($request) {
                parse_str($request->body(), $fields);
                if (($fields['IPGmethod'] ?? null) !== 'IPGPaymentToken') {
                    return null;
                }

                return Http::response(['Status' => '0', 'Token' => 'test-token-'.Str::random(12)]);
            },
        ]);
    }
}
