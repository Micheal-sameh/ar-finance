<?php

use App\Modules\Booking\Http\Controllers\BookableItemController;
use App\Modules\Booking\Http\Controllers\BookingController;
use App\Modules\Booking\Http\Controllers\BookingTypeController;
use Illuminate\Support\Facades\Route;

// Read-only views, open to a Platform Admin browsing "All tenants" without
// an active tenant — same split as the rest of web.php. The calendar's
// global scope auto-skips when there's no active tenant (see TenantScope),
// so this renders every tenant's bookings combined and read-only, exactly
// like Accounts/Journals do; booking/cancelling/paying still need a
// specific tenant, so those stay in the tenant-gated group below.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('bookable-items', [BookableItemController::class, 'index'])->name('bookable-items.index');
    Route::get('booking-types', [BookingTypeController::class, 'index'])->name('booking-types.index');
    Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/calendar', [BookingController::class, 'calendar'])->name('bookings.calendar');
});

Route::middleware(['auth', 'active', 'tenant'])->group(function () {
    Route::get('bookable-items/options', [BookableItemController::class, 'options'])->name('bookable-items.options');
    Route::resource('bookable-items', BookableItemController::class)->only(['store', 'update', 'destroy']);

    Route::resource('booking-types', BookingTypeController::class)->only(['store', 'update', 'destroy']);

    Route::resource('bookings', BookingController::class)->only(['store']);
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('bookings/{booking}/deposit', [BookingController::class, 'payDeposit'])->name('bookings.pay-deposit');
    Route::post('bookings/{booking}/pay', [BookingController::class, 'markPaid'])->name('bookings.mark-paid');
});
