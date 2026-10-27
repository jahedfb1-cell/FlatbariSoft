<?php

use App\Http\Controllers\AppDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Android app: open /download/app on the phone, tap download, install.
Route::get('/download/app', [AppDownloadController::class, 'page'])->name('app.download');
Route::get('/download/apk', [AppDownloadController::class, 'apk'])->name('app.apk');
