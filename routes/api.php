<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\MonthlyClosingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\BuildingController;
use App\Http\Controllers\Api\FlatController;
use App\Http\Controllers\Api\GasController;
use App\Http\Controllers\Api\FlatOwnerController;
use App\Http\Controllers\Api\OwnerDocumentController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TenantDocumentController;
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

    Route::get('/persons/{person}/documents', [OwnerDocumentController::class, 'index'])->middleware('permission:document.view');
    Route::post('/persons/{person}/documents', [OwnerDocumentController::class, 'store'])->middleware('permission:document.manage');
    Route::delete('/owner-documents/{ownerDocument}', [OwnerDocumentController::class, 'destroy'])->middleware('permission:document.manage');

    Route::get('/tenants/{tenant}/documents', [TenantDocumentController::class, 'index'])->middleware('permission:document.view');
    Route::post('/tenants/{tenant}/documents', [TenantDocumentController::class, 'store'])->middleware('permission:document.manage');
    Route::delete('/tenant-documents/{tenantDocument}', [TenantDocumentController::class, 'destroy'])->middleware('permission:document.manage');

    Route::get('/staff', [StaffController::class, 'index'])->middleware('permission:staff.view');
    Route::post('/staff', [StaffController::class, 'store'])->middleware('permission:staff.manage');
    Route::get('/staff/{staff}', [StaffController::class, 'show'])->middleware('permission:staff.view');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->middleware('permission:staff.manage');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->middleware('permission:staff.manage');
});

Route::prefix('billing')->middleware('auth:sanctum')->group(function () {
    Route::post('/generate', [BillingController::class, 'generate'])->middleware('permission:billing.manage');
    Route::get('/{year}/{month}', [BillingController::class, 'show'])->middleware('permission:billing.view');
});

Route::prefix('payments')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [PaymentController::class, 'index'])->middleware('permission:payment.view');
    Route::post('/', [PaymentController::class, 'store'])->middleware('permission:payment.create');
    Route::post('/{payment}/approve', [PaymentController::class, 'approve'])->middleware('permission:payment.approve');
});

Route::prefix('expenses')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ExpenseController::class, 'index'])->middleware('permission:expense.view');
    Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:expense.create');
    Route::post('/{expense}/approve', [ExpenseController::class, 'approve'])->middleware('permission:expense.approve');
});

Route::post('/closing', [MonthlyClosingController::class, 'close'])
    ->middleware(['auth:sanctum', 'permission:accounting.manage']);

Route::prefix('bank-accounts')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [BankAccountController::class, 'index'])->middleware('permission:accounting.view');
    Route::post('/', [BankAccountController::class, 'store'])->middleware('permission:accounting.manage');
});

Route::prefix('gas')->middleware('auth:sanctum')->group(function () {
    Route::get('/consumers', [GasController::class, 'consumers'])->middleware('permission:gas.view');
    Route::post('/readings', [GasController::class, 'storeReading'])->middleware('permission:gas.manage');
    Route::post('/periods/{billingPeriodId}/apply', [GasController::class, 'applyToBills'])->middleware('permission:gas.manage');
    Route::get('/periods/{billingPeriodId}/system-loss/{year}/{month}', [GasController::class, 'systemLoss'])->middleware('permission:gas.view');
});
