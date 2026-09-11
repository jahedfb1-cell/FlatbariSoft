<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuildingController;
use App\Http\Controllers\Api\FlatController;
use App\Http\Controllers\Api\FlatOwnerController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

// Public health check — lets the frontend (and infra monitoring later)
// confirm the API is reachable before any auth exists.
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:otp-request');
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('masters')->middleware('auth:sanctum')->group(function () {
    Route::get('/buildings', [BuildingController::class, 'index'])->middleware('permission:building.view');
    Route::get('/buildings/{building}', [BuildingController::class, 'show'])->middleware('permission:building.view');
    Route::put('/buildings/{building}', [BuildingController::class, 'update'])->middleware('permission:building.update');

    Route::get('/flats', [FlatController::class, 'index'])->middleware('permission:flat.view');
    Route::post('/flats', [FlatController::class, 'store'])->middleware('permission:flat.create');
    Route::get('/flats/{flat}', [FlatController::class, 'show'])->middleware('permission:flat.view');
    Route::put('/flats/{flat}', [FlatController::class, 'update'])->middleware('permission:flat.update');
    Route::delete('/flats/{flat}', [FlatController::class, 'destroy'])->middleware('permission:flat.delete');

    Route::post('/flats/{flat}/owners', [FlatOwnerController::class, 'store'])->middleware('permission:flat.update');
    Route::delete('/flats/{flat}/owners/{flatParty}', [FlatOwnerController::class, 'destroy'])->middleware('permission:flat.update');

    Route::post('/flats/{flat}/tenants', [TenantController::class, 'store'])->middleware('permission:tenant.manage');
    Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->middleware('permission:tenant.manage');
    Route::post('/tenants/{tenant}/vacate', [TenantController::class, 'vacate'])->middleware('permission:tenant.manage');

    Route::get('/persons', [PersonController::class, 'index'])->middleware('permission:person.view');
    Route::post('/persons', [PersonController::class, 'store'])->middleware('permission:person.manage');
    Route::get('/persons/{person}', [PersonController::class, 'show'])->middleware('permission:person.view');
    Route::put('/persons/{person}', [PersonController::class, 'update'])->middleware('permission:person.manage');
    Route::delete('/persons/{person}', [PersonController::class, 'destroy'])->middleware('permission:person.manage');
});
