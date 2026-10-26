<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\BankTransactionController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\CurrencyRevaluationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevComponentsController;
use App\Http\Controllers\DevSwitchUserController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\ReportController as PlatformReportController;
use App\Http\Controllers\Platform\SwitchTenantController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TenantSwitchController;
use App\Http\Controllers\TwoFactorChallengeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

// Email-or-phone + password sign-in. A correct password is never enough on
// its own: LoginController::store parks the user in the session and the
// two-factor routes below finish (or first enrol) the MFA step.
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');

    Route::get('/two-factor', [TwoFactorChallengeController::class, 'show'])->name('two-factor.show');
    Route::post('/two-factor', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:10,1')->name('two-factor.store');
});

if (app()->environment('local')) {
    // Not behind auth: a static preview of the ui/ primitive library.
    // Local only so it never ships to production.
    Route::get('/dev/components', DevComponentsController::class)->name('dev.components');

    // Quick "log in as" user switcher in the navbar (see DevUserSwitcher.tsx
    // and HandleInertiaRequests' `devUsers` share). Gated to local by
    // DevSwitchUserRequest::authorize() too — route is only registered
    // here for defense in depth.
    Route::post('/dev-switch-user/{user}', DevSwitchUserController::class)
        ->middleware(['auth', 'throttle:10,1'])
        ->name('dev-switch-user');
}

// Not behind `tenant`: a Platform Admin with no active tenant must still be
// able to log out rather than being bounced to /platform.
Route::get('two-factor/recovery-codes', [TwoFactorChallengeController::class, 'recoveryCodes'])->middleware('auth')->name('two-factor.recovery-codes');
Route::post('logout', LogoutController::class)->middleware(['auth', 'throttle:240,1'])->name('logout');

// Platform Admin area: cross-tenant dashboard/reports and tenant
// management. Deliberately outside the `tenant` middleware below — a
// Platform Admin reaches these precisely when they have no active tenant.
Route::middleware(['auth', 'active', 'permission:platform.access', 'throttle:240,1'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/', PlatformDashboardController::class)->name('dashboard');

    Route::post('tenants/{tenant}/switch', [SwitchTenantController::class, 'switch'])->name('switch-tenant');
    Route::post('stop-impersonating', [SwitchTenantController::class, 'stop'])->name('stop-impersonating');

    Route::resource('tenants', TenantController::class)->only(['index', 'store', 'update']);

    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}/tenant', [UserController::class, 'assignTenant'])->name('users.assign-tenant');
    Route::put('users/{user}/role', [UserController::class, 'assignRole'])->name('users.assign-role');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('trial-balance', [PlatformReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('profit-and-loss', [PlatformReportController::class, 'profitAndLoss'])->name('profit-and-loss');
        Route::get('balance-sheet', [PlatformReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('cash-flow', [PlatformReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('vat-return', [PlatformReportController::class, 'vatReturn'])->name('vat-return');
        Route::get('aging', [PlatformReportController::class, 'aging'])->name('aging');
    });
});

// Read-only "All tenants" list views for a Platform Admin browsing without
// an active tenant (see TenantContext::isViewingAllTenants() — each of
// these controllers' index() adds a tenant_name column in that mode). Not
// gated by `tenant`: an ordinary user always has a tenant_id, so this
// group behaves identically to the gated one below for them. Every other
// action (create/update/destroy/...) stays behind `tenant`, since those
// require impersonating a specific tenant.
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('tenants/{tenant}/switch', TenantSwitchController::class)->name('tenant.switch');
    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('accounts/options', [AccountController::class, 'options'])->name('accounts.options');
    Route::get('journals', [JournalEntryController::class, 'index'])->name('journals.index');
    Route::get('cost-centers', [CostCenterController::class, 'index'])->name('cost-centers.index');
    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('bills', [BillController::class, 'index'])->name('bills.index');
    Route::get('fixed-assets', [FixedAssetController::class, 'index'])->name('fixed-assets.index');
    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('payroll-runs', [PayrollRunController::class, 'index'])->name('payroll-runs.index');
    Route::get('bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('exchange-rates', [ExchangeRateController::class, 'index'])->name('exchange-rates.index');
    Route::get('reports/general-ledger', [ReportController::class, 'generalLedger'])->name('reports.general-ledger');
    Route::get('reports/general-ledger/export', [ReportController::class, 'generalLedgerExport'])->name('reports.general-ledger.export');
});

Route::middleware(['auth', 'active', 'tenant', 'throttle:240,1'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');

    Route::get('users/export', [UserController::class, 'export'])->name('users.export');
    Route::resource('users', UserController::class)->only(['update']);

    Route::post('accounts/import', [AccountController::class, 'import'])->name('accounts.import');
    Route::get('accounts/import-template', [AccountController::class, 'importTemplate'])->name('accounts.import-template');
    Route::get('accounts/export', [AccountController::class, 'export'])->name('accounts.export');
    Route::resource('accounts', AccountController::class)->except(['create', 'edit', 'index']);

    Route::get('journals/export', [JournalEntryController::class, 'export'])->name('journals.export');
    Route::resource('journals', JournalEntryController::class)->only(['create', 'store', 'show']);

    Route::get('cost-centers/options', [CostCenterController::class, 'options'])->name('cost-centers.options');
    Route::get('cost-centers/export', [CostCenterController::class, 'export'])->name('cost-centers.export');
    Route::post('cost-centers/import', [CostCenterController::class, 'import'])->name('cost-centers.import');
    Route::get('cost-centers/import-template', [CostCenterController::class, 'importTemplate'])->name('cost-centers.import-template');
    Route::resource('cost-centers', CostCenterController::class)->only(['store', 'update', 'destroy']);

    Route::get('clients/export', [ClientController::class, 'export'])->name('clients.export');
    Route::resource('clients', ClientController::class)->only(['show', 'store', 'update', 'destroy']);
    Route::get('vendors/export', [VendorController::class, 'export'])->name('vendors.export');
    Route::resource('vendors', VendorController::class)->only(['show', 'store', 'update', 'destroy']);

    Route::get('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::resource('invoices', InvoiceController::class)->only(['create', 'store', 'show']);
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment'])->name('invoices.record-payment');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

    Route::get('expenses/export', [ExpenseController::class, 'export'])->name('expenses.export');
    Route::resource('expenses', ExpenseController::class)->only(['create', 'store', 'show']);
    Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('expenses/{expense}/pay', [ExpenseController::class, 'markPaid'])->name('expenses.mark-paid');

    Route::get('purchase-orders/export', [PurchaseOrderController::class, 'export'])->name('purchase-orders.export');
    Route::resource('purchase-orders', PurchaseOrderController::class)->only(['create', 'store', 'show']);
    Route::post('purchase-orders/{purchase_order}/send', [PurchaseOrderController::class, 'send'])->name('purchase-orders.send');
    Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::post('purchase-orders/{purchase_order}/convert-to-bill', [PurchaseOrderController::class, 'convertToBill'])->name('purchase-orders.convert-to-bill');

    Route::get('bills/export', [BillController::class, 'export'])->name('bills.export');
    Route::resource('bills', BillController::class)->only(['create', 'store', 'show']);
    Route::post('bills/{bill}/approve', [BillController::class, 'approve'])->name('bills.approve');
    Route::post('bills/{bill}/pay', [BillController::class, 'markPaid'])->name('bills.mark-paid');
    Route::get('bills/{bill}/pdf', [BillController::class, 'pdf'])->name('bills.pdf');

    Route::get('fixed-assets/export', [FixedAssetController::class, 'export'])->name('fixed-assets.export');
    Route::resource('fixed-assets', FixedAssetController::class)->only(['create', 'store', 'show']);
    Route::post('fixed-assets/{fixed_asset}/post-depreciation', [FixedAssetController::class, 'postDepreciation'])->name('fixed-assets.post-depreciation');
    Route::post('fixed-assets/run-depreciation', [FixedAssetController::class, 'runAll'])->name('fixed-assets.run-depreciation');

    Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::resource('employees', EmployeeController::class)->only(['store', 'update', 'destroy']);

    Route::get('payroll-runs/export', [PayrollRunController::class, 'export'])->name('payroll-runs.export');
    Route::resource('payroll-runs', PayrollRunController::class)->only(['create', 'store', 'show']);
    Route::post('payroll-runs/{payroll_run}/approve', [PayrollRunController::class, 'approve'])->name('payroll-runs.approve');
    Route::post('payroll-runs/{payroll_run}/pay', [PayrollRunController::class, 'markPaid'])->name('payroll-runs.mark-paid');
    Route::get('payroll-runs/{payroll_run}/payslips/{payslip}/pdf', [PayrollRunController::class, 'payslipPdf'])->name('payroll-runs.payslips.pdf');

    Route::get('bank-accounts/export', [BankAccountController::class, 'export'])->name('bank-accounts.export');
    Route::resource('bank-accounts', BankAccountController::class)->only(['show', 'store', 'update', 'destroy']);
    Route::post('bank-accounts/{bank_account}/import', [BankAccountController::class, 'import'])->name('bank-accounts.import');

    Route::post('bank-accounts/{bank_account}/auto-match', [BankAccountController::class, 'autoMatch'])->name('bank-accounts.auto-match');
    Route::post('bank-accounts/{bank_account}/reconciliations', [BankReconciliationController::class, 'store'])->name('bank-reconciliations.store');
    Route::get('bank-accounts/{bank_account}/reconciliation-preview', [BankReconciliationController::class, 'preview'])->name('bank-reconciliations.preview');
    Route::get('bank-reconciliations/{bank_reconciliation}', [BankReconciliationController::class, 'show'])->name('bank-reconciliations.show');
    Route::delete('bank-reconciliations/{bank_reconciliation}', [BankReconciliationController::class, 'destroy'])->name('bank-reconciliations.destroy');
    Route::get('bank-reconciliations/{bank_reconciliation}/export', [BankReconciliationController::class, 'export'])->name('bank-reconciliations.export');

    Route::post('bank-transactions/{bank_transaction}/match', [BankTransactionController::class, 'match'])->name('bank-transactions.match');
    Route::post('bank-transactions/{bank_transaction}/unmatch', [BankTransactionController::class, 'unmatch'])->name('bank-transactions.unmatch');
    Route::post('bank-transactions/{bank_transaction}/create-and-match', [BankTransactionController::class, 'createAndMatch'])->name('bank-transactions.create-and-match');

    Route::get('exchange-rates/export', [ExchangeRateController::class, 'export'])->name('exchange-rates.export');
    Route::post('exchange-rates/sync', [ExchangeRateController::class, 'sync'])->name('exchange-rates.sync');

    Route::get('revaluation', [CurrencyRevaluationController::class, 'index'])->name('revaluation.index');
    Route::get('revaluation/export', [CurrencyRevaluationController::class, 'export'])->name('revaluation.export');
    Route::post('revaluation', [CurrencyRevaluationController::class, 'revalue'])->name('revaluation.revalue');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('trial-balance/pdf', [ReportController::class, 'trialBalancePdf'])->name('trial-balance.pdf');
        Route::get('profit-and-loss', [ReportController::class, 'profitAndLoss'])->name('profit-and-loss');
        Route::get('profit-and-loss/export', [ReportController::class, 'profitAndLossExport'])->name('profit-and-loss.export');
        Route::get('profit-and-loss/pdf', [ReportController::class, 'profitAndLossPdf'])->name('profit-and-loss.pdf');
        Route::get('balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('balance-sheet/pdf', [ReportController::class, 'balanceSheetPdf'])->name('balance-sheet.pdf');
        Route::get('cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('cash-flow/pdf', [ReportController::class, 'cashFlowPdf'])->name('cash-flow.pdf');
        Route::get('vat-return', [ReportController::class, 'vatReturn'])->name('vat-return');
        Route::get('aging', [ReportController::class, 'aging'])->name('aging');
    });
});
