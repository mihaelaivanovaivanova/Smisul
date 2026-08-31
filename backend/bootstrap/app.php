<?php

use App\Http\Middleware\EnsureUserIsAdministrator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias(['admin' => EnsureUserIsAdministrator::class]);

        // This backend has no server-rendered "login" page - auth:sanctum's
        // default unauthenticated-guest redirect target is route('login'),
        // which resolves to POST /api/v1/auth/login. Fine for an XHR (gets
        // a plain 401, since expectsJson() is true), but a *browser*
        // navigation that isn't authenticated (e.g. a plain <a> link whose
        // session had expired, like the admin label-download link) sends
        // Accept: text/html, so Laravel instead tries to redirect there -
        // a GET on a POST-only route, always a 405. Point guests at the
        // real frontend login page instead.
        $middleware->redirectGuestsTo(fn () => rtrim((string) config('app.frontend_url'), '/').'/login');
    })
    // Every listener in this app is wired up explicitly via Event::listen()
    // in AppServiceProvider::boot() (see its own comment on why: Laravel
    // 11+ ships no default EventServiceProvider). Auto-discovery is on by
    // default regardless of that, and silently double-registers any
    // listener whose class also matches its convention (a public handle()
    // method type-hinted with the event) - every listener in app/Listeners
    // was firing twice per event (confirmed via `artisan event:list`),
    // including every order/review/favorite notification email and
    // Laravel's own email-verification listener. Disabling discovery
    // entirely keeps this app's actual, single, explicit registration path.
    ->withEvents(discover: false)
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
