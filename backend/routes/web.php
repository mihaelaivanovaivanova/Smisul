<?php

use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// This backend is an API-only service — the React SPA (see /frontend) is
// the actual entry point users interact with. This route just confirms
// the API is reachable.
Route::get('/', fn () => response()->json(['service' => 'Smisul API', 'status' => 'ok']));

// Deliberately outside routes/api.php (which gets an automatic /api
// prefix — see bootstrap/app.php) so this sits at the conventional
// /sitemap.xml path. Production still needs a reverse-proxy rule
// forwarding the frontend domain's /sitemap.xml here — see
// docs/legal-gdpr-seo.md.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
