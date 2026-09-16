<?php

namespace Database\Seeders;

use App\DTOs\CreateBankAccountData;
use App\DTOs\CreateBillData;
use App\DTOs\CreateClientData;
use App\DTOs\CreateCostCenterData;
use App\DTOs\CreateEmployeeData;
use App\DTOs\CreateExpenseData;
use App\DTOs\CreateFixedAssetData;
use App\DTOs\CreateInvoiceData;
use App\DTOs\CreatePayrollRunData;
use App\DTOs\CreateVendorData;
use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BankAccountService;
use App\Services\BillService;
use App\Services\ClientService;
use App\Services\CostCenterService;
use App\Services\EmployeeService;
use App\Services\ExpenseService;
use App\Services\FixedAssetService;
use App\Services\InvoiceService;
use App\Services\PayrollRunService;
use App\Services\VendorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Populates a tenant with realistic, cross-module sample data so the app
 * can be exercised manually: clients/vendors, invoices and bills in a mix
 * of draft/sent/paid states, standalone expenses, a depreciating fixed
 * asset, a payroll run, and activity spanning last year and this year (so
 * the 3100/3200 net-income split in ReportService has something to show).
 *
 * Everything is posted through the normal Services — never raw journal
 * inserts — so it exercises the same validation and GL-posting logic a
 * real user would trigger.
 */
class DemoDataSeeder extends Seeder
{
    private array $accounts = [];

    public function run(int $tenantId, int $userId): void
    {
        $this->call(ChartOfAccountsSeeder::class, false, ['tenantId' => $tenantId]);

        // Everything below relies on BelongsToTenant's auth()-based
        // tenant_id stamping and on auth()->id() for created_by, since
        // there's no HTTP request/session in a seeder.
        Auth::login(User::find($userId));

        $this->loadAccounts($tenantId);
        $this->seedLeafAccounts($tenantId);

        $costCenters = $this->seedCostCenters();
        $this->seedBankAccount();
        $clients = $this->seedClients();
        $vendors = $this->seedVendors();

        $this->seedInvoices($clients);
        $this->seedBills($vendors, $costCenters);
        $this->seedExpenses($vendors, $costCenters);
        $this->seedFixedAsset();
        $this->seedPayroll();
    }

    private function loadAccounts(int $tenantId): void
    {
        $this->accounts = Account::where('tenant_id', $tenantId)->get()->keyBy('code')->all();
    }

    private function account(string $code): Account
    {
        return $this->accounts[$code];
    }

    /**
     * Transactional leaf accounts the chart-of-accounts seeder doesn't
     * create (it only lays out the category headers) — these are what
     * invoices, bills, expenses, and payroll actually post to.
     */
    private function seedLeafAccounts(int $tenantId): void
    {
        $leaves = [
            ['code' => '1110', 'name' => 'Equipment', 'type' => AccountType::Asset, 'parent' => '1100'],
            ['code' => '1120', 'name' => 'Accumulated Depreciation — Equipment', 'type' => AccountType::Asset, 'parent' => '1100', 'normal_balance' => NormalBalance::Credit],
            ['code' => '1210', 'name' => 'Cash and Bank', 'type' => AccountType::Asset, 'parent' => '1200'],
            ['code' => '1220', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset, 'parent' => '1200'],
            ['code' => '1230', 'name' => 'VAT Receivable', 'type' => AccountType::Asset, 'parent' => '1200'],
            ['code' => '2210', 'name' => 'Accounts Payable', 'type' => AccountType::Liability, 'parent' => '2200'],
            ['code' => '2220', 'name' => 'VAT Payable', 'type' => AccountType::Liability, 'parent' => '2200'],
            ['code' => '2230', 'name' => 'Salaries Payable', 'type' => AccountType::Liability, 'parent' => '2200'],
            ['code' => '2240', 'name' => 'Payroll Deductions Payable', 'type' => AccountType::Liability, 'parent' => '2200'],
            ['code' => '5110', 'name' => 'Cost of Services', 'type' => AccountType::Expense, 'parent' => '5100'],
            ['code' => '5210', 'name' => 'Office & Admin Expenses', 'type' => AccountType::Expense, 'parent' => '5200'],
            ['code' => '5220', 'name' => 'Salaries Expense', 'type' => AccountType::Expense, 'parent' => '5200'],
            ['code' => '5310', 'name' => 'Depreciation Expense', 'type' => AccountType::Expense, 'parent' => '5300'],
        ];

        foreach ($leaves as $leaf) {
            $this->accounts[$leaf['code']] = Account::updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $leaf['code']],
                [
                    'name' => $leaf['name'],
                    'type' => $leaf['type'],
                    'normal_balance' => $leaf['normal_balance'] ?? $leaf['type']->defaultNormalBalance(),
                    'parent_id' => $this->account($leaf['parent'])->id,
                    'is_active' => true,
                    'is_deletable' => false,
                ],
            );
        }
    }

    private function seedCostCenters(): array
    {
        $costCenters = app(CostCenterService::class);

        return [
            'operations' => $costCenters->create(CreateCostCenterData::fromArray([
                'name' => 'Operations',
                'type' => 'profit',
            ])),
            'admin' => $costCenters->create(CreateCostCenterData::fromArray([
                'name' => 'Administration',
                'type' => 'cost',
            ])),
        ];
    }

    private function seedBankAccount(): void
    {
        app(BankAccountService::class)->create(CreateBankAccountData::fromArray([
            'name' => 'Main Bank Account',
            'account_id' => $this->account('1210')->id,
            'bank_name' => 'National Bank of Egypt',
            'account_number' => '100-200-300',
            'currency' => 'EGP',
        ]));
    }

    private function seedClients(): array
    {
        $clients = app(ClientService::class);

        return collect([
            ['name' => 'Nile Trading Co.', 'email' => 'ap@niletrading.example', 'tax_number' => 'EG-100001'],
            ['name' => 'Cairo Retail Group', 'email' => 'accounts@cairoretail.example', 'tax_number' => 'EG-100002'],
            ['name' => 'Delta Exports Ltd', 'email' => 'finance@deltaexports.example', 'tax_number' => 'EG-100003'],
        ])->map(fn ($data) => $clients->create(CreateClientData::fromArray([
            'name' => $data['name'],
            'email' => $data['email'],
            'tax_number' => $data['tax_number'],
            'currency' => 'EGP',
        ])))->all();
    }

    private function seedVendors(): array
    {
        $vendors = app(VendorService::class);

        return collect([
            ['name' => 'Alpha Office Supplies', 'email' => 'sales@alphaoffice.example'],
            ['name' => 'Beta IT Services', 'email' => 'billing@betait.example'],
        ])->map(fn ($data) => $vendors->create(CreateVendorData::fromArray([
            'name' => $data['name'],
            'email' => $data['email'],
            'payment_terms' => 'Net 30',
        ])))->all();
    }

    private function seedInvoices(array $clients): void
    {
        $invoices = app(InvoiceService::class);

        // Prior year, fully paid — feeds the 3200 (Retained Earnings) balance.
        $invoice = $invoices->create(CreateInvoiceData::fromArray([
            'client_id' => $clients[0]->id,
            'invoice_number' => 'INV-2025-0001',
            'issue_date' => '2025-06-15',
            'due_date' => '2025-07-15',
            'currency' => 'EGP',
            'receivable_account_id' => $this->account('1220')->id,
            'tax_payable_account_id' => $this->account('2220')->id,
            'lines' => [
                ['description' => 'Consulting services', 'quantity' => 1, 'unit_price' => 40000, 'tax_rate' => 14, 'account_id' => $this->account('4000')->id],
            ],
        ]));
        $invoices->send($invoice);
        $invoices->recordPayment($invoice, $this->account('1210')->id);

        // Current year, sent and paid.
        $invoice = $invoices->create(CreateInvoiceData::fromArray([
            'client_id' => $clients[1]->id,
            'invoice_number' => 'INV-2026-0001',
            'issue_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'currency' => 'EGP',
            'receivable_account_id' => $this->account('1220')->id,
            'tax_payable_account_id' => $this->account('2220')->id,
            'lines' => [
                ['description' => 'Software subscription — annual', 'quantity' => 1, 'unit_price' => 25000, 'tax_rate' => 14, 'account_id' => $this->account('4000')->id],
            ],
        ]));
        $invoices->send($invoice);
        $invoices->recordPayment($invoice, $this->account('1210')->id);

        // Current year, sent but unpaid — still awaiting payment.
        $invoice = $invoices->create(CreateInvoiceData::fromArray([
            'client_id' => $clients[2]->id,
            'invoice_number' => 'INV-2026-0002',
            'issue_date' => now()->subDays(5)->toDateString(),
            'due_date' => now()->addDays(25)->toDateString(),
            'currency' => 'EGP',
            'receivable_account_id' => $this->account('1220')->id,
            'tax_payable_account_id' => $this->account('2220')->id,
            'lines' => [
                ['description' => 'Product sales', 'quantity' => 10, 'unit_price' => 1200, 'tax_rate' => 14, 'account_id' => $this->account('4000')->id],
            ],
        ]));
        $invoices->send($invoice);

        // Draft — never sent, so it never touches the ledger.
        $invoices->create(CreateInvoiceData::fromArray([
            'client_id' => $clients[0]->id,
            'invoice_number' => 'INV-2026-0003',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'EGP',
            'receivable_account_id' => $this->account('1220')->id,
            'tax_payable_account_id' => $this->account('2220')->id,
            'lines' => [
                ['description' => 'Quoted work — pending confirmation', 'quantity' => 1, 'unit_price' => 8000, 'tax_rate' => 14, 'account_id' => $this->account('4000')->id],
            ],
        ]));
    }

    private function seedBills(array $vendors, array $costCenters): void
    {
        $bills = app(BillService::class);

        // Prior year, approved and paid.
        $bill = $bills->create(CreateBillData::fromArray([
            'vendor_id' => $vendors[0]->id,
            'bill_number' => 'BILL-2025-0001',
            'bill_date' => '2025-07-01',
            'due_date' => '2025-07-31',
            'payable_account_id' => $this->account('2210')->id,
            'tax_receivable_account_id' => $this->account('1230')->id,
            'cost_center_id' => $costCenters['admin']->id,
            'lines' => [
                ['description' => 'Office furniture', 'quantity' => 1, 'unit_price' => 12000, 'tax_rate' => 14, 'account_id' => $this->account('5210')->id],
            ],
        ]));
        $bills->approve($bill);
        $bills->markPaid($bill, $this->account('1210')->id);

        // Current year, approved and paid.
        $bill = $bills->create(CreateBillData::fromArray([
            'vendor_id' => $vendors[1]->id,
            'bill_number' => 'BILL-2026-0001',
            'bill_date' => now()->subDays(15)->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'payable_account_id' => $this->account('2210')->id,
            'tax_receivable_account_id' => $this->account('1230')->id,
            'cost_center_id' => $costCenters['operations']->id,
            'lines' => [
                ['description' => 'Cloud hosting — monthly', 'quantity' => 1, 'unit_price' => 6000, 'tax_rate' => 14, 'account_id' => $this->account('5110')->id],
            ],
        ]));
        $bills->approve($bill);
        $bills->markPaid($bill, $this->account('1210')->id);

        // Current year, approved but unpaid.
        $bill = $bills->create(CreateBillData::fromArray([
            'vendor_id' => $vendors[0]->id,
            'bill_number' => 'BILL-2026-0002',
            'bill_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(27)->toDateString(),
            'payable_account_id' => $this->account('2210')->id,
            'tax_receivable_account_id' => $this->account('1230')->id,
            'cost_center_id' => $costCenters['admin']->id,
            'lines' => [
                ['description' => 'Office supplies', 'quantity' => 1, 'unit_price' => 3000, 'tax_rate' => 14, 'account_id' => $this->account('5210')->id],
            ],
        ]));
        $bills->approve($bill);

        // Draft — never approved, so it never touches the ledger.
        $bills->create(CreateBillData::fromArray([
            'vendor_id' => $vendors[1]->id,
            'bill_number' => 'BILL-2026-0003',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'payable_account_id' => $this->account('2210')->id,
            'tax_receivable_account_id' => $this->account('1230')->id,
            'cost_center_id' => $costCenters['operations']->id,
            'lines' => [
                ['description' => 'IT support retainer — awaiting approval', 'quantity' => 1, 'unit_price' => 4000, 'tax_rate' => 14, 'account_id' => $this->account('5110')->id],
            ],
        ]));
    }

    private function seedExpenses(array $vendors, array $costCenters): void
    {
        $expenses = app(ExpenseService::class);

        $expense = $expenses->create(CreateExpenseData::fromArray([
            'description' => 'Office rent — current month',
            'account_id' => $this->account('5210')->id,
            'amount' => 15000,
            'date' => now()->subDays(10)->toDateString(),
            'cost_center_id' => $costCenters['admin']->id,
            'payable_account_id' => $this->account('2210')->id,
        ]));
        $expenses->approve($expense);
        $expenses->markPaid($expense, $this->account('1210')->id);

        // Pending — not yet approved.
        $expenses->create(CreateExpenseData::fromArray([
            'description' => 'Business travel',
            'account_id' => $this->account('5210')->id,
            'amount' => 2500,
            'date' => now()->subDays(2)->toDateString(),
            'vendor_id' => $vendors[0]->id,
            'cost_center_id' => $costCenters['operations']->id,
            'payable_account_id' => $this->account('2210')->id,
        ]));
    }

    private function seedFixedAsset(): void
    {
        $fixedAssets = app(FixedAssetService::class);

        $asset = $fixedAssets->create(CreateFixedAssetData::fromArray([
            'name' => 'Office Equipment',
            'purchase_date' => '2025-01-01',
            'cost' => 60000,
            'salvage_value' => 6000,
            'useful_life_years' => 5,
            'asset_account_id' => $this->account('1110')->id,
            'depreciation_account_id' => $this->account('5310')->id,
            'accumulated_depreciation_account_id' => $this->account('1120')->id,
        ]));

        $month = $asset->purchase_date->copy()->addMonth()->startOfMonth();
        $cutoff = now()->startOfMonth();

        while ($month->lte($cutoff)) {
            $fixedAssets->postDepreciation($asset, $month->format('Y-m'));
            $month->addMonth();
        }
    }

    private function seedPayroll(): void
    {
        $employees = app(EmployeeService::class);

        $aya = $employees->create(CreateEmployeeData::fromArray([
            'name' => 'Aya Hassan',
            'email' => 'aya.hassan@avarewase-demo.example',
            'job_title' => 'Accountant',
            'salary' => 18000,
            'hire_date' => '2024-09-01',
        ]));
        $omar = $employees->create(CreateEmployeeData::fromArray([
            'name' => 'Omar Khaled',
            'email' => 'omar.khaled@avarewase-demo.example',
            'job_title' => 'Sales Executive',
            'salary' => 15000,
            'hire_date' => '2025-02-15',
        ]));

        $payrollRuns = app(PayrollRunService::class);

        $run = $payrollRuns->create(CreatePayrollRunData::fromArray([
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'pay_date' => now()->endOfMonth()->toDateString(),
            'expense_account_id' => $this->account('5220')->id,
            'payable_account_id' => $this->account('2230')->id,
            'deductions_payable_account_id' => $this->account('2240')->id,
            'payslips' => [
                ['employee_id' => $aya->id, 'gross_pay' => 18000, 'deductions' => 1800],
                ['employee_id' => $omar->id, 'gross_pay' => 15000, 'deductions' => 1500],
            ],
        ]));

        $payrollRuns->approve($run);
    }
}
