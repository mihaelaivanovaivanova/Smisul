<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdministrator()) {
            abort(403, 'This action requires administrator privileges.');
        }

        return $next($request);
    }
}
