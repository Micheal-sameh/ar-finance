<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $payrollExpense;

    private Account $salariesPayable;

    private Account $deductionsPayable;

    private Account $bank;

    private Employee $employeeA;

    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->payrollExpense = $this->account('7000', 'Payroll Expense', AccountType::Expense, 'debit');
        $this->salariesPayable = $this->account('2100', 'Salaries Payable', AccountType::Liability, 'credit');
        $this->deductionsPayable = $this->account('2110', 'Payroll Deductions Payable', AccountType::Liability, 'credit');
        $this->bank = $this->account('1000', 'Bank', AccountType::Asset, 'debit');

        $this->employeeA = Employee::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Alice', 'salary' => 3000, 'hire_date' => '2025-01-01',
        ]);
        $this->employeeB = Employee::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bob', 'salary' => 2000, 'hire_date' => '2025-01-01',
        ]);
    }

    private function account(string $code, string $name, AccountType $type, string $normalBalance): Account
    {
        return Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
        ]);
    }

    private function createDraftRun(array $payslips): int
    {
        $response = $this->actingAs($this->user)->post(route('payroll-runs.store'), [
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'pay_date' => '2026-02-01',
            'expense_account_id' => $this->payrollExpense->id,
            'payable_account_id' => $this->salariesPayable->id,
            'deductions_payable_account_id' => $this->deductionsPayable->id,
            'payslips' => $payslips,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        return \App\Models\PayrollRun::latest('id')->firstOrFail()->id;
    }

    public function test_draft_payroll_run_does_not_post_to_the_ledger(): void
    {
        $runId = $this->createDraftRun([
            ['employee_id' => $this->employeeA->id, 'gross_pay' => 3000, 'deductions' => 0],
        ]);

        $this->assertDatabaseHas('payroll_runs', ['id' => $runId, 'status' => 'draft']);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_approving_without_deductions_posts_expense_against_payable(): void
    {
        $runId = $this->createDraftRun([
            ['employee_id' => $this->employeeA->id, 'gross_pay' => 3000, 'deductions' => 0],
            ['employee_id' => $this->employeeB->id, 'gross_pay' => 2000, 'deductions' => 0],
        ]);

        $response = $this->actingAs($this->user)->post(route('payroll-runs.approve', $runId));
        $response->assertRedirect();

        $this->assertDatabaseHas('payroll_runs', ['id' => $runId, 'status' => 'approved']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->payrollExpense->id, 'debit' => 5000]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->salariesPayable->id, 'credit' => 5000]);
    }

    public function test_approving_with_deductions_splits_the_credit_across_payable_and_deductions(): void
    {
        $runId = $this->createDraftRun([
            ['employee_id' => $this->employeeA->id, 'gross_pay' => 3000, 'deductions' => 300],
        ]);

        $response = $this->actingAs($this->user)->post(route('payroll-runs.approve', $runId));
        $response->assertRedirect();

        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->payrollExpense->id, 'debit' => 3000]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->salariesPayable->id, 'credit' => 2700]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->deductionsPayable->id, 'credit' => 300]);
    }

    public function test_marking_paid_settles_payable_against_cash(): void
    {
        $runId = $this->createDraftRun([
            ['employee_id' => $this->employeeA->id, 'gross_pay' => 3000, 'deductions' => 0],
        ]);
        $this->actingAs($this->user)->post(route('payroll-runs.approve', $runId));

        $response = $this->actingAs($this->user)->post(route('payroll-runs.mark-paid', $runId), [
            'payment_account_id' => $this->bank->id,
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('payroll_runs', ['id' => $runId, 'status' => 'paid']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->salariesPayable->id, 'debit' => 3000]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bank->id, 'credit' => 3000]);
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_cannot_approve_a_run_without_a_deductions_account_when_deductions_exist(): void
    {
        $response = $this->actingAs($this->user)->post(route('payroll-runs.store'), [
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'pay_date' => '2026-02-01',
            'expense_account_id' => $this->payrollExpense->id,
            'payable_account_id' => $this->salariesPayable->id,
            'payslips' => [
                ['employee_id' => $this->employeeA->id, 'gross_pay' => 3000, 'deductions' => 300],
            ],
        ]);

        $response->assertSessionHasErrors('deductions_payable_account_id');
        $this->assertDatabaseCount('payroll_runs', 0);
    }
}
