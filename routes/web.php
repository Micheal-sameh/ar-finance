<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevComponentsController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Not behind auth: a static preview of the ui/ primitive library, useful
// without a live SSO server in local dev.
Route::get('/dev/components', DevComponentsController::class)->name('dev.components');

// avarewase/sso-client logs the user into a normal session guard (see
// avarewase.login / avarewase.callback, registered by the package) — so
// every app route past that point is protected the standard Laravel way,
// not by the package's separate bearer-token `avarewase.auth` middleware
// (that one's for a resource server taking an Authorization header on
// every request, which doesn't fit a cookie-session Inertia app).
Route::middleware(['auth'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('accounts/options', [AccountController::class, 'options'])->name('accounts.options');
    Route::resource('accounts', AccountController::class)->except(['create', 'edit']);

    Route::resource('journals', JournalEntryController::class)->only(['index', 'create', 'store', 'show']);

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('general-ledger', [ReportController::class, 'generalLedger'])->name('general-ledger');
    });
});
