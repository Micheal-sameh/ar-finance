<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\CostCenter;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');
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

    public function test_can_create_a_cost_center_with_a_budget(): void
    {
        $response = $this->actingAs($this->user)->post(route('cost-centers.store'), [
            'name' => 'Marketing',
            'type' => 'cost',
            'budget' => 5000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cost_centers', ['name' => 'Marketing', 'type' => 'cost', 'budget' => 5000]);
    }

    public function test_cost_center_with_tagged_expenses_cannot_be_deleted(): void
    {
        $center = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Ops', 'type' => 'cost']);
        $expenseAccount = $this->account('6000', 'Ops Expense', AccountType::Expense, 'debit');
        $ap = $this->account('2000', 'Accounts Payable', AccountType::Liability, 'credit');

        $this->actingAs($this->user)->post(route('expenses.store'), [
            'description' => 'Team lunch',
            'account_id' => $expenseAccount->id,
            'amount' => 50,
            'date' => now()->toDateString(),
            'payable_account_id' => $ap->id,
            'cost_center_id' => $center->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('cost-centers.destroy', $center));

        $response->assertRedirect();
        $this->assertDatabaseHas('cost_centers', ['id' => $center->id]);
    }

    public function test_budget_vs_actual_summary_reflects_tagged_journal_activity(): void
    {
        $center = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Marketing', 'type' => 'cost', 'budget' => 1000]);
        $expenseAccount = $this->account('6000', 'Ad Spend', AccountType::Expense, 'debit');
        $ap = $this->account('2000', 'Accounts Payable', AccountType::Liability, 'credit');

        $this->actingAs($this->user)->post(route('expenses.store'), [
            'description' => 'Facebook ads',
            'account_id' => $expenseAccount->id,
            'amount' => 300,
            'date' => now()->toDateString(),
            'payable_account_id' => $ap->id,
            'cost_center_id' => $center->id,
        ]);
        $expenseId = \App\Models\Expense::where('description', 'Facebook ads')->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('expenses.approve', $expenseId));

        $response = $this->actingAs($this->user)->get(route('cost-centers.index', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/CostCenters/Index')
            ->where('summary.0.cost_center_id', $center->id)
            ->where('summary.0.budget', 1000)
            ->where('summary.0.spent', 300)
            ->where('summary.0.utilization_percent', 30)
        );
    }

    public function test_journal_entry_line_can_be_tagged_with_a_cost_center(): void
    {
        $center = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'R&D', 'type' => 'cost']);
        $cash = $this->account('1000', 'Cash', AccountType::Asset, 'debit');
        $expense = $this->account('6100', 'Software', AccountType::Expense, 'debit');

        $response = $this->actingAs($this->user)->post(route('journals.store'), [
            'date' => now()->toDateString(),
            'description' => 'Software subscription',
            'lines' => [
                ['account_id' => $expense->id, 'debit' => 99, 'credit' => 0, 'cost_center_id' => $center->id],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 99],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('journal_lines', ['account_id' => $expense->id, 'cost_center_id' => $center->id, 'debit' => 99]);
    }
}
