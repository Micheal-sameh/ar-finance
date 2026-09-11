<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevComponentsController;
use App\Http\Controllers\DevLoginController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

// Not behind auth: a static preview of the ui/ primitive library, useful
// without a live SSO server in local dev.
Route::get('/dev/components', DevComponentsController::class)->name('dev.components');

// Branded landing page with a "Continue with Avarewase" button — guests
// land here (see redirectGuestsTo in bootstrap/app.php) rather than being
// silently bounced straight to the SSO server.
Route::get('/login', LoginController::class)->name('login');

// Email/password fallback so the app can be exercised without a live SSO
// server. Gated to local by DevLoginController/DevLoginRequest as well —
// route is only registered here for defense in depth.
if (app()->environment('local')) {
    Route::post('/dev-login', DevLoginController::class)
        ->middleware('throttle:10,1')
        ->name('dev-login');
}

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

    Route::get('cost-centers/options', [CostCenterController::class, 'options'])->name('cost-centers.options');
    Route::resource('cost-centers', CostCenterController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('clients', ClientController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('vendors', VendorController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment'])->name('invoices.record-payment');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');

    Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('expenses/{expense}/pay', [ExpenseController::class, 'markPaid'])->name('expenses.mark-paid');

    Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('purchase-orders/{purchase_order}/send', [PurchaseOrderController::class, 'send'])->name('purchase-orders.send');
    Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::post('purchase-orders/{purchase_order}/convert-to-bill', [PurchaseOrderController::class, 'convertToBill'])->name('purchase-orders.convert-to-bill');

    Route::resource('bills', BillController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('bills/{bill}/approve', [BillController::class, 'approve'])->name('bills.approve');
    Route::post('bills/{bill}/pay', [BillController::class, 'markPaid'])->name('bills.mark-paid');

    Route::resource('fixed-assets', FixedAssetController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('fixed-assets/{fixed_asset}/post-depreciation', [FixedAssetController::class, 'postDepreciation'])->name('fixed-assets.post-depreciation');
    Route::post('fixed-assets/run-depreciation', [FixedAssetController::class, 'runAll'])->name('fixed-assets.run-depreciation');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('general-ledger', [ReportController::class, 'generalLedger'])->name('general-ledger');
        Route::get('profit-and-loss', [ReportController::class, 'profitAndLoss'])->name('profit-and-loss');
        Route::get('balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
    });
});
