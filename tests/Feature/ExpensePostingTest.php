<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensePostingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $expenseAccount;

    private Account $ap;

    private Account $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->expenseAccount = $this->account('6000', 'Office Supplies', AccountType::Expense, 'debit');
        $this->ap = $this->account('2000', 'Accounts Payable', AccountType::Liability, 'credit');
        $this->bank = $this->account('1000', 'Bank', AccountType::Asset, 'debit');
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

    private function createPendingExpense(): int
    {
        $response = $this->actingAs($this->user)->post(route('expenses.store'), [
            'description' => 'Printer paper',
            'account_id' => $this->expenseAccount->id,
            'amount' => 75.50,
            'date' => now()->toDateString(),
            'payable_account_id' => $this->ap->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        return \App\Models\Expense::where('description', 'Printer paper')->firstOrFail()->id;
    }

    public function test_pending_expense_does_not_post_to_the_ledger(): void
    {
        $expenseId = $this->createPendingExpense();

        $this->assertDatabaseHas('expenses', ['id' => $expenseId, 'status' => 'pending']);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_approving_an_expense_posts_expense_against_payable(): void
    {
        $expenseId = $this->createPendingExpense();

        $response = $this->actingAs($this->user)->post(route('expenses.approve', $expenseId));
        $response->assertRedirect();

        $this->assertDatabaseHas('expenses', ['id' => $expenseId, 'status' => 'approved']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->expenseAccount->id, 'debit' => 75.50]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->ap->id, 'credit' => 75.50]);
    }

    public function test_marking_paid_settles_payable_against_cash(): void
    {
        $expenseId = $this->createPendingExpense();
        $this->actingAs($this->user)->post(route('expenses.approve', $expenseId));

        $response = $this->actingAs($this->user)->post(route('expenses.mark-paid', $expenseId), [
            'payment_account_id' => $this->bank->id,
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('expenses', ['id' => $expenseId, 'status' => 'paid']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->ap->id, 'debit' => 75.50]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bank->id, 'credit' => 75.50]);
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_cannot_mark_paid_before_approval(): void
    {
        $expenseId = $this->createPendingExpense();

        $response = $this->actingAs($this->user)->post(route('expenses.mark-paid', $expenseId), [
            'payment_account_id' => $this->bank->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('journal_entries', 0);
    }
}
