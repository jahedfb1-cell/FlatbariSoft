<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\MonthlyClosingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\BuildingController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\ConstructionDueController;
use App\Http\Controllers\Api\FlatController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\MyController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\GasController;
use App\Http\Controllers\Api\HallController;
use App\Http\Controllers\Api\MyHallController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
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
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
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
    Route::get('/tenants/{tenant}/kyc', [\App\Http\Controllers\Api\TenantKycController::class, 'show'])->middleware('permission:tenant.manage');
    Route::put('/tenants/{tenant}/kyc', [\App\Http\Controllers\Api\TenantKycController::class, 'update'])->middleware('permission:tenant.manage');
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
    Route::post('/periods/{year}/{month}', [\App\Http\Controllers\Api\BillingPeriodController::class, 'findOrCreate'])->middleware('permission:gas.manage');
});

Route::prefix('payments')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [PaymentController::class, 'index'])->middleware('permission:payment.view');
    Route::post('/', [PaymentController::class, 'store'])->middleware('permission:payment.create');
    Route::post('/{payment}/approve', [PaymentController::class, 'approve'])->middleware('permission:payment.approve');
    Route::get('/{payment}/receipt', [PaymentController::class, 'receipt'])->middleware('permission:payment.view');
});

Route::prefix('expenses')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ExpenseController::class, 'index'])->middleware('permission:expense.view');
    Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:expense.create');
    Route::get('/attachments/{attachment}', [ExpenseController::class, 'file'])->middleware('permission:expense.view');
    Route::delete('/attachments/{attachment}', [ExpenseController::class, 'removeFile'])->middleware('permission:expense.create');
    Route::get('/{expense}', [ExpenseController::class, 'show'])->middleware('permission:expense.view');
    Route::post('/{expense}/attachments', [ExpenseController::class, 'attach'])->middleware('permission:expense.create');
    Route::post('/{expense}/approve', [ExpenseController::class, 'approve'])->middleware('permission:expense.approve');
});

Route::get('/expense-heads', [\App\Http\Controllers\Api\ExpenseHeadController::class, 'index'])
    ->middleware(['auth:sanctum', 'permission:expense.view']);

Route::post('/closing', [MonthlyClosingController::class, 'close'])
    ->middleware(['auth:sanctum', 'permission:accounting.manage']);

Route::prefix('bank-accounts')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [BankAccountController::class, 'index'])->middleware('permission:accounting.view');
    Route::post('/', [BankAccountController::class, 'store'])->middleware('permission:accounting.manage');
    Route::put('/{bankAccount}', [BankAccountController::class, 'update'])->middleware('permission:accounting.manage');
});

Route::prefix('construction-dues')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ConstructionDueController::class, 'index'])->middleware('permission:due.view');
    Route::post('/', [ConstructionDueController::class, 'store'])->middleware('permission:due.manage');
    Route::get('/aging-report', [ConstructionDueController::class, 'agingReport'])->middleware('permission:due.view');
    Route::post('/installments/{installment}/pay', [ConstructionDueController::class, 'payInstallment'])->middleware('permission:due.manage');
});

Route::prefix('assets')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [AssetController::class, 'index'])->middleware('permission:asset.view');
    Route::post('/', [AssetController::class, 'store'])->middleware('permission:asset.manage');
    Route::get('/{asset}', [AssetController::class, 'show'])->middleware('permission:asset.view');
    Route::put('/{asset}', [AssetController::class, 'update'])->middleware('permission:asset.manage');
    Route::post('/{asset}/service-logs', [AssetController::class, 'addServiceLog'])->middleware('permission:asset.manage');
});

Route::prefix('meetings')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [MeetingController::class, 'index'])->middleware('permission:meeting.view');
    Route::post('/', [MeetingController::class, 'store'])->middleware('permission:meeting.manage');
    Route::post('/{meeting}/resolutions', [MeetingController::class, 'addResolution'])->middleware('permission:meeting.manage');
    Route::post('/resolutions/{resolution}/attachment', [MeetingController::class, 'attach'])->middleware('permission:meeting.manage');
    Route::get('/resolutions/{resolution}/attachment', [MeetingController::class, 'attachment'])->middleware('permission:meeting.view');
});

Route::prefix('reports')->middleware(['auth:sanctum', 'permission:accounting.view'])->group(function () {
    Route::get('/trial-balance', [ReportController::class, 'trialBalance']);
    Route::get('/income-statement', [ReportController::class, 'incomeStatement']);
    Route::get('/balance-sheet', [ReportController::class, 'balanceSheet']);
    Route::get('/dashboard', [ReportController::class, 'dashboard']);

    Route::get('/trial-balance/pdf', [ReportController::class, 'trialBalancePdf']);
    Route::get('/trial-balance/excel', [ReportController::class, 'trialBalanceExcel']);
    Route::get('/balance-sheet/pdf', [ReportController::class, 'balanceSheetPdf']);
    Route::get('/balance-sheet/excel', [ReportController::class, 'balanceSheetExcel']);
    Route::get('/income-statement/pdf', [ReportController::class, 'incomeStatementPdf']);
    Route::get('/income-statement/excel', [ReportController::class, 'incomeStatementExcel']);
});

Route::prefix('me')->middleware('auth:sanctum')->group(function () {
    Route::get('/flats', [MyController::class, 'flats']);
    Route::get('/bills', [MyController::class, 'bills']);
    Route::get('/documents', [MyController::class, 'documents']);
    Route::get('/complaints', [MyController::class, 'complaints']);
    Route::post('/complaints', [MyController::class, 'storeComplaint']);

    // community hall: any logged-in resident (scoped to their own flats inside the controller)
    Route::get('/hall', [MyHallController::class, 'hall']);
    Route::get('/hall/availability', [MyHallController::class, 'availability']);
    Route::get('/hall/quote', [MyHallController::class, 'quote']);
    Route::get('/hall/bookings', [MyHallController::class, 'index']);
    Route::post('/hall/bookings', [MyHallController::class, 'store']);
    Route::post('/hall/bookings/{booking}/cancel', [MyHallController::class, 'cancel']);
});

Route::prefix('notices')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [NoticeController::class, 'index']); // any logged-in resident can read published notices
    Route::post('/', [NoticeController::class, 'store'])->middleware('permission:notice.manage');
});

Route::prefix('complaints')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ComplaintController::class, 'index'])->middleware('permission:complaint.view');
    Route::post('/', [ComplaintController::class, 'store'])->middleware('permission:complaint.manage');
    Route::put('/{complaint}/status', [ComplaintController::class, 'updateStatus'])->middleware('permission:complaint.manage');
});

Route::prefix('gas')->middleware('auth:sanctum')->group(function () {
    Route::get('/consumers', [GasController::class, 'consumers'])->middleware('permission:gas.view');
    Route::get('/summary', [GasController::class, 'summary'])->middleware('permission:gas.view');
    Route::post('/consumers', [GasController::class, 'storeConsumer'])->middleware('permission:gas.manage');
    Route::put('/consumers/{consumer}', [GasController::class, 'updateConsumer'])->middleware('permission:gas.manage');
    Route::post('/readings', [GasController::class, 'storeReading'])->middleware('permission:gas.manage');
    Route::post('/periods/{billingPeriodId}/apply', [GasController::class, 'applyToBills'])->middleware('permission:gas.manage');
    Route::get('/periods/{billingPeriodId}/system-loss/{year}/{month}', [GasController::class, 'systemLoss'])->middleware('permission:gas.view');
    Route::get('/purchases', [\App\Http\Controllers\Api\GasPurchaseController::class, 'index'])->middleware('permission:gas.view');
    // the ledger: every purchase in date order with running totals, the voucher no and bill photo status
    Route::get('/purchases/ledger', [\App\Http\Controllers\Api\GasLedgerController::class, 'index'])->middleware('permission:gas.view');
    Route::get('/purchases/ledger/pdf', [\App\Http\Controllers\Api\GasLedgerController::class, 'pdf'])->middleware('permission:gas.view');
    Route::get('/purchases/ledger/excel', [\App\Http\Controllers\Api\GasLedgerController::class, 'excel'])->middleware('permission:gas.view');
    Route::post('/purchases', [\App\Http\Controllers\Api\GasPurchaseController::class, 'store'])->middleware('permission:gas.manage');
});

Route::prefix('construction-materials')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\ConstructionMaterialPurchaseController::class, 'index'])->middleware('permission:expense.view');
    Route::post('/', [\App\Http\Controllers\Api\ConstructionMaterialPurchaseController::class, 'store'])->middleware('permission:expense.create');
});

// Purchase-invoice photos. The same three operations exist for each purchase
// type; only the URL prefix and the permission differ, so they are registered
// in a loop (the type is handed to the controller as a route default).
foreach ([
    'gas' => ['prefix' => 'gas/purchases', 'view' => 'gas.view', 'manage' => 'gas.manage'],
    'materials' => ['prefix' => 'construction-materials', 'view' => 'expense.view', 'manage' => 'expense.create'],
] as $invoiceType => $invoiceCfg) {
    $invoiceBase = "{$invoiceCfg['prefix']}/{purchase}/invoice";
    $invoiceController = \App\Http\Controllers\Api\PurchaseInvoiceController::class;

    Route::get($invoiceBase, [$invoiceController, 'show'])
        ->defaults('type', $invoiceType)->middleware(['auth:sanctum', "permission:{$invoiceCfg['view']}"]);
    Route::get("{$invoiceBase}/print", [$invoiceController, 'print'])
        ->defaults('type', $invoiceType)->middleware(['auth:sanctum', "permission:{$invoiceCfg['view']}"]);
    Route::post($invoiceBase, [$invoiceController, 'store'])
        ->defaults('type', $invoiceType)->middleware(['auth:sanctum', "permission:{$invoiceCfg['manage']}"]);
}

Route::get('/invoices/audit-pack', [\App\Http\Controllers\Api\InvoiceAuditController::class, 'pack'])
    ->middleware(['auth:sanctum', 'permission:accounting.view']);

// Community hall (office side). view = read; request = take a booking (stays PENDING);
// approve = approve/reject/cancel/complete; payments = take or refund money; manage = settings and rates.
Route::prefix('hall')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [HallController::class, 'show'])->middleware('permission:hall.view');
    Route::put('/', [HallController::class, 'updateSettings'])->middleware('permission:hall.manage');
    Route::get('/availability', [HallController::class, 'availability'])->middleware('permission:hall.view');
    Route::get('/quote', [HallController::class, 'quote'])->middleware('permission:hall.view');

    Route::get('/bookings', [HallController::class, 'index'])->middleware('permission:hall.view');
    Route::post('/bookings', [HallController::class, 'store'])->middleware('permission:hall.request');
    Route::get('/bookings/{booking}', [HallController::class, 'showBooking'])->middleware('permission:hall.view');
    Route::post('/bookings/{booking}/approve', [HallController::class, 'approve'])->middleware('permission:hall.approve');
    Route::post('/bookings/{booking}/reject', [HallController::class, 'reject'])->middleware('permission:hall.approve');
    Route::post('/bookings/{booking}/cancel', [HallController::class, 'cancel'])->middleware('permission:hall.approve');
    Route::post('/bookings/{booking}/complete', [HallController::class, 'complete'])->middleware('permission:hall.approve');
    Route::post('/bookings/{booking}/payments', [HallController::class, 'recordPayment'])->middleware('permission:hall.payments');
});

// Users and roles. view = see the lists; manage = create/edit logins, reset passwords;
// role.manage = change what each role may do. Residents' accounts are created automatically when they
// are assigned to a flat; this is for committee members, the building manager and other staff.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:user.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:user.manage');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:user.manage');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:user.manage');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:user.view');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:role.manage');
    Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->middleware('permission:role.manage');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:role.manage');
});

// Parking. view = see slots/holders/charges; manage = slots, allocations, generate monthly charges;
// payments = collect money. Residents see only their own flat's slots through /me/parking.
Route::prefix('parking')->middleware('auth:sanctum')->group(function () {
    Route::get('/slots', [\App\Http\Controllers\Api\ParkingController::class, 'slots'])->middleware('permission:parking.view');
    Route::post('/slots', [\App\Http\Controllers\Api\ParkingController::class, 'storeSlot'])->middleware('permission:parking.manage');
    Route::put('/slots/{slot}', [\App\Http\Controllers\Api\ParkingController::class, 'updateSlot'])->middleware('permission:parking.manage');
    Route::post('/slots/{slot}/allocate', [\App\Http\Controllers\Api\ParkingController::class, 'allocate'])->middleware('permission:parking.manage');
    Route::post('/allocations/{allocation}/release', [\App\Http\Controllers\Api\ParkingController::class, 'release'])->middleware('permission:parking.manage');

    Route::get('/charges', [\App\Http\Controllers\Api\ParkingController::class, 'charges'])->middleware('permission:parking.view');
    Route::post('/charges/generate', [\App\Http\Controllers\Api\ParkingController::class, 'generate'])->middleware('permission:parking.manage');
    Route::get('/charges/{charge}', [\App\Http\Controllers\Api\ParkingController::class, 'showCharge'])->middleware('permission:parking.view');
    Route::post('/charges/{charge}/payments', [\App\Http\Controllers\Api\ParkingController::class, 'pay'])->middleware('permission:parking.payments');
    Route::get('/summary', [\App\Http\Controllers\Api\ParkingController::class, 'summary'])->middleware('permission:parking.view');
});
Route::get('/me/parking', [\App\Http\Controllers\Api\MyParkingController::class, 'index'])->middleware('auth:sanctum');

// Collections from flat owners, in separate books (construction cost, flat, parking sale; {book} is the code).
// view = statements and ledgers; collect = record a receipt; manage = targets, loading earlier collections, reversals.
Route::prefix('contributions')->middleware('auth:sanctum')->group(function () {
    $c = \App\Http\Controllers\Api\ContributionController::class;
    Route::get('/books', [$c, 'books'])->middleware('permission:contribution.view');
    Route::get('/books/{book}/statement', [$c, 'statement'])->middleware('permission:contribution.view');
    Route::get('/books/{book}/statement/pdf', [$c, 'pdf'])->middleware('permission:contribution.view');
    Route::get('/books/{book}/statement/excel', [$c, 'excel'])->middleware('permission:contribution.view');
    Route::get('/books/{book}/ledgers/pdf', [$c, 'allLedgersPdf'])->middleware('permission:contribution.view');
    Route::put('/books/{book}/default-target', [$c, 'setDefaultTarget'])->middleware('permission:contribution.manage');
    Route::put('/books/{book}/flats/{flat}/target', [$c, 'setFlatTarget'])->middleware('permission:contribution.manage');
    Route::post('/books/{book}/import', [$c, 'import'])->middleware('permission:contribution.manage');
    Route::get('/flats/{flat}/ledgers', [$c, 'flatLedgers'])->middleware('permission:contribution.view');
    Route::get('/flats/{flat}/ledgers/pdf', [$c, 'ledgerPdf'])->middleware('permission:contribution.view');
    Route::post('/flats/{flat}/collections', [$c, 'store'])->middleware('permission:contribution.collect');
    Route::post('/collections/{contribution}/reverse', [$c, 'reverse'])->middleware('permission:contribution.manage');
});
Route::get('/me/contributions', [\App\Http\Controllers\Api\MyContributionController::class, 'index'])->middleware('auth:sanctum');
Route::get('/me/contributions/pdf', [\App\Http\Controllers\Api\MyContributionController::class, 'pdf'])->middleware('auth:sanctum');

// Monthly fund statements (receipts & payments per fund) and the treasury entries behind them.
// Reading needs accounting.view; making an entry (opening balance, bank, loans) needs treasury.manage.
Route::prefix('treasury')->middleware('auth:sanctum')->group(function () {
    $c = \App\Http\Controllers\Api\TreasuryController::class;
    Route::get('/statement', [$c, 'statement'])->middleware('permission:accounting.view');
    Route::get('/statement/pdf', [$c, 'pdf'])->middleware('permission:accounting.view');
    Route::get('/statement/excel', [$c, 'excel'])->middleware('permission:accounting.view');
    Route::get('/summary', [$c, 'summary'])->middleware('permission:accounting.view');
    Route::get('/entries', [$c, 'entries'])->middleware('permission:accounting.view');
    Route::post('/opening-balance', [$c, 'openingBalance'])->middleware('permission:treasury.manage');
    Route::post('/bank-deposit', [$c, 'deposit'])->middleware('permission:treasury.manage');
    Route::post('/bank-withdrawal', [$c, 'withdraw'])->middleware('permission:treasury.manage');
    Route::post('/bank-profit', [$c, 'profit'])->middleware('permission:treasury.manage');
    Route::post('/loans', [$c, 'lend'])->middleware('permission:treasury.manage');
    Route::post('/loan-repayments', [$c, 'repay'])->middleware('permission:treasury.manage');
    Route::post('/entries/{entry}/reverse', [$c, 'reverse'])->middleware('permission:treasury.manage');
});

// Supplier accounts: what was bought from each gas / construction-material supplier, what was paid, what is owed.
foreach (['gas' => 'gas.view', 'materials' => 'expense.view'] as $supplierKind => $supplierPermission) {
    $supplierBase = $supplierKind === 'gas' ? 'gas/suppliers' : 'construction-materials/suppliers';
    $supplierController = \App\Http\Controllers\Api\SupplierLedgerController::class;
    foreach ([
        '' => 'index', '/ledger' => 'ledger', '/ledger/pdf' => 'pdf', '/ledger/excel' => 'excel',
    ] as $suffix => $action) {
        Route::get($supplierBase.$suffix, [$supplierController, $action])
            ->defaults('kind', $supplierKind)->middleware(['auth:sanctum', "permission:{$supplierPermission}"]);
    }
}

// Home screen: one call, each block shown only to people allowed to open that part of the system.
Route::get('/dashboard/overview', [\App\Http\Controllers\Api\DashboardController::class, 'overview'])->middleware('auth:sanctum');

// Office settings (late fee, due day, reminders, SMS switch) and the SMS log.
Route::prefix('settings')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\SettingsController::class, 'show'])->middleware('permission:setting.manage');
    Route::put('/', [\App\Http\Controllers\Api\SettingsController::class, 'update'])->middleware('permission:setting.manage');
    Route::get('/sms-logs', [\App\Http\Controllers\Api\SettingsController::class, 'smsLogs'])->middleware('permission:setting.manage');
});

Route::get('/audit-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'index'])->middleware(['auth:sanctum', 'permission:audit.view']);

// Yearly budget against actual spending.
Route::get('/budget', [\App\Http\Controllers\Api\BudgetController::class, 'show'])->middleware(['auth:sanctum', 'permission:budget.view']);
Route::put('/budget', [\App\Http\Controllers\Api\BudgetController::class, 'save'])->middleware(['auth:sanctum', 'permission:budget.manage']);

// The gate book.
Route::prefix('visitors')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\VisitorController::class, 'index'])->middleware('permission:visitor.view');
    Route::post('/', [\App\Http\Controllers\Api\VisitorController::class, 'store'])->middleware('permission:visitor.manage');
    Route::post('/{visitor}/checkout', [\App\Http\Controllers\Api\VisitorController::class, 'checkout'])->middleware('permission:visitor.manage');
});
Route::get('/me/visitors', [\App\Http\Controllers\Api\VisitorController::class, 'mine'])->middleware('auth:sanctum');

Route::get('/backup/download', [\App\Http\Controllers\Api\BackupController::class, 'download'])->middleware(['auth:sanctum', 'permission:backup.manage']);

// A resident's own payments (any logged-in resident; each action is scoped to the flats they hold).
Route::get('/me/payments', [\App\Http\Controllers\Api\MyPaymentController::class, 'index'])->middleware('auth:sanctum');
Route::post('/me/payments', [\App\Http\Controllers\Api\MyPaymentController::class, 'store'])->middleware('auth:sanctum');
Route::get('/me/payments/{payment}/receipt', [\App\Http\Controllers\Api\MyPaymentController::class, 'receipt'])->middleware('auth:sanctum');

// A tenant's own KYC form (the tenant of the flat; scoped to the logged-in person's active tenancy).
Route::get('/me/kyc', [\App\Http\Controllers\Api\TenantKycController::class, 'showMine'])->middleware('auth:sanctum');
Route::put('/me/kyc', [\App\Http\Controllers\Api\TenantKycController::class, 'updateMine'])->middleware('auth:sanctum');
