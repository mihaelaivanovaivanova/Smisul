<?php

use Illuminate\Support\Facades\Route;

// This backend is an API-only service — the React SPA (see /frontend) is
// the actual entry point users interact with. This route just confirms
// the API is reachable.
Route::get('/', fn () => response()->json(['service' => 'Smisul API', 'status' => 'ok']));
