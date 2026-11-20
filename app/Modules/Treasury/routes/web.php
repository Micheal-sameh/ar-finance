<?php

use App\Modules\Treasury\Http\Controllers\TreasuryController;
use Illuminate\Support\Facades\Route;

// Treasury is an optional module: `module:treasury` answers 404 unless it has
// been switched on for the tenant (the treasury tenant and every tenant that
// deposits with it).
Route::middleware(['auth', 'active', 'tenant', 'module:treasury', 'throttle:240,1'])->prefix('treasury')->name('treasury.')->group(function () {
    Route::get('/', [TreasuryController::class, 'index'])->name('index');
    Route::post('requests', [TreasuryController::class, 'request'])->name('request');
    Route::post('fund', [TreasuryController::class, 'fund'])->name('fund');
    Route::post('move', [TreasuryController::class, 'move'])->name('move');
    Route::post('{treasury_transfer}/accept', [TreasuryController::class, 'accept'])->name('accept');
    Route::post('{treasury_transfer}/reject', [TreasuryController::class, 'reject'])->name('reject');
    Route::post('{treasury_transfer}/void', [TreasuryController::class, 'void'])->name('void');
});
