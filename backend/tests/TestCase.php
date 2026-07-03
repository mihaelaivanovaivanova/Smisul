<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
    }
}
