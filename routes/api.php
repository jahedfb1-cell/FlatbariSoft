<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Temporary Phase 0.1 smoke-test route — confirms Sanctum token auth
// and the spatie/laravel-permission middleware alias both resolve.
// Will be replaced by the real auth routes in Phase 0.4.
Route::middleware('auth:sanctum')->get('/ping', function (Request $request) {
    return response()->json([
        'ok' => true,
        'user_id' => $request->user()->id,
    ]);
});
