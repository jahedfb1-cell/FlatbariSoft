<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public health check — lets the frontend (and infra monitoring later)
// confirm the API is reachable before any auth exists (Phase 0.4).
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]);
});

// Temporary Phase 0.1 smoke-test route — confirms Sanctum token auth
// and the spatie/laravel-permission middleware alias both resolve.
// Will be replaced by the real auth routes in Phase 0.4.
Route::middleware('auth:sanctum')->get('/ping', function (Request $request) {
    return response()->json([
        'ok' => true,
        'user_id' => $request->user()->id,
    ]);
});
