<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\PayrollRun;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $revenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->revenue = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '4000',
            'name' => 'Sales Revenue',
            'type' => AccountType::Revenue,
            'normal_balance' => 'credit',
        ]);
    }

    public function test_invoice_pdf_route_streams_a_pdf(): void
    {
        $client = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'شركة أكمي', 'currency' => 'USD']);

        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-0001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft',
            'currency' => 'USD',
            'exchange_rate' => 1,
            'receivable_account_id' => $this->revenue->id,
        ]);
        $invoice->lines()->create([
            'description' => 'Consulting', 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 0, 'account_id' => $this->revenue->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('invoices.pdf', $invoice->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_bill_pdf_route_streams_a_pdf(): void
    {
        $vendor = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Supplies']);

        $bill = Bill::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'bill_number' => 'BILL-0001',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft',
            'payable_account_id' => $this->revenue->id,
        ]);
        $bill->lines()->create([
            'description' => 'Supplies', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0, 'account_id' => $this->revenue->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('bills.pdf', $bill->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_payslip_pdf_route_streams_a_pdf(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Alice', 'salary' => 3000, 'hire_date' => '2025-01-01',
        ]);
        $payrollExpense = Account::create([
            'tenant_id' => $this->tenant->id, 'code' => '7000', 'name' => 'Payroll Expense', 'type' => AccountType::Expense, 'normal_balance' => 'debit',
        ]);
        $payable = Account::create([
            'tenant_id' => $this->tenant->id, 'code' => '2100', 'name' => 'Salaries Payable', 'type' => AccountType::Liability, 'normal_balance' => 'credit',
        ]);

        $run = PayrollRun::create([
            'tenant_id' => $this->tenant->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'pay_date' => '2026-02-01',
            'status' => 'draft',
            'expense_account_id' => $payrollExpense->id,
            'payable_account_id' => $payable->id,
        ]);
        $payslip = $run->payslips()->create([
            'employee_id' => $employee->id, 'gross_pay' => 3000, 'deductions' => 0, 'net_pay' => 3000,
        ]);

        $response = $this->actingAs($this->user)->get(route('payroll-runs.payslips.pdf', [$run->id, $payslip->id]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_cannot_view_a_payslip_pdf_from_a_different_payroll_run(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Alice', 'salary' => 3000, 'hire_date' => '2025-01-01',
        ]);
        $payrollExpense = Account::create([
            'tenant_id' => $this->tenant->id, 'code' => '7000', 'name' => 'Payroll Expense', 'type' => AccountType::Expense, 'normal_balance' => 'debit',
        ]);
        $payable = Account::create([
            'tenant_id' => $this->tenant->id, 'code' => '2100', 'name' => 'Salaries Payable', 'type' => AccountType::Liability, 'normal_balance' => 'credit',
        ]);

        $runA = PayrollRun::create([
            'tenant_id' => $this->tenant->id, 'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'pay_date' => '2026-02-01', 'status' => 'draft', 'expense_account_id' => $payrollExpense->id, 'payable_account_id' => $payable->id,
        ]);
        $runB = PayrollRun::create([
            'tenant_id' => $this->tenant->id, 'period_start' => '2026-02-01', 'period_end' => '2026-02-28',
            'pay_date' => '2026-03-01', 'status' => 'draft', 'expense_account_id' => $payrollExpense->id, 'payable_account_id' => $payable->id,
        ]);
        $payslip = $runA->payslips()->create(['employee_id' => $employee->id, 'gross_pay' => 3000, 'deductions' => 0, 'net_pay' => 3000]);

        $response = $this->actingAs($this->user)->get(route('payroll-runs.payslips.pdf', [$runB->id, $payslip->id]));

        $response->assertNotFound();
    }
}
