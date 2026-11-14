<?php

use App\Modules\Donations\Http\Controllers\DonationController;
use App\Modules\Donations\Http\Controllers\DonorController;
use Illuminate\Support\Facades\Route;

// Donations is an optional module: `module:donations` answers 404 unless a
// Platform Admin or Portal Manager has switched it on for the tenant.
Route::middleware(['auth', 'active', 'tenant', 'module:donations', 'throttle:240,1'])->group(function () {
    Route::get('donations', [DonationController::class, 'index'])->name('donations.index');
    Route::post('donations', [DonationController::class, 'store'])->name('donations.store');
    Route::post('donations/{donation}/cancel', [DonationController::class, 'cancel'])->name('donations.cancel');
    Route::get('donations/{donation}/receipt', [DonationController::class, 'receipt'])->name('donations.receipt');

    Route::get('donors', [DonorController::class, 'index'])->name('donors.index');
    Route::post('donors', [DonorController::class, 'store'])->name('donors.store');
    Route::put('donors/{donor}', [DonorController::class, 'update'])->name('donors.update');
});
