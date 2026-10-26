<?php

namespace Tests\Feature;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\AccountType;
use App\Enums\JournalSourceType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\CostCenter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\JournalService;
use App\Services\ReportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $bank;

    private Account $sales;

    private Account $rent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->bank = $this->account('1000', 'Bank', AccountType::Asset, 'debit');
        $this->sales = $this->account('4000', 'Sales', AccountType::Revenue, 'credit');
        $this->rent = $this->account('5000', 'Rent', AccountType::Expense, 'debit');
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

    /**
     * @param  array<int, int|float>  $months  Month number => amount; unspecified months are zero.
     * @return array{account_id: int, cost_center_id: ?int, months: array<int, int|float>}
     */
    private function line(Account $account, array $months, ?int $costCenterId = null): array
    {
        return ['account_id' => $account->id, 'cost_center_id' => $costCenterId, 'months' => $months];
    }

    private function spend(string $date, float $amount, ?int $costCenterId = null): void
    {
        $this->actingAs($this->user);

        app(JournalService::class)->postJournalEntry(new CreateJournalEntryData(
            date: $date,
            description: 'Rent',
            reference: null,
            sourceType: JournalSourceType::Manual,
            sourceId: null,
            createdBy: $this->user->id,
            lines: [
                new JournalLineData(accountId: $this->rent->id, debit: $amount, credit: 0, costCenterId: $costCenterId),
                new JournalLineData(accountId: $this->bank->id, debit: 0, credit: $amount),
            ],
        ));
    }

    public function test_budget_against_actual_shows_variance_percent_and_health(): void
    {
        $this->actingAs($this->user);
        app(BudgetService::class)->save(2026, [
            $this->line($this->rent, [1 => 100, 2 => 100]),
            $this->line($this->sales, [1 => 500]),
        ]);

        $this->spend('2026-01-10', 150);

        $report = app(BudgetService::class)->budgetVsActual('2026-01-01', '2026-01-31');

        $this->assertTrue($report['has_budget']);
        $rent = collect($report['expenses'])->firstWhere('account_id', $this->rent->id);
        $this->assertEquals(100, $rent['budget']);
        $this->assertEquals(150, $rent['actual']);
        $this->assertEquals(-50, $rent['variance']);
        $this->assertEquals(150.0, $rent['percent_used']);
        $this->assertSame('bad', $rent['health']);

        $sales = collect($report['revenue'])->firstWhere('account_id', $this->sales->id);
        $this->assertEquals(500, $sales['budget']);
        $this->assertEquals(-500, $sales['variance']);
        $this->assertSame('bad', $sales['health']);

        $this->assertEquals(400, $report['net_profit']['budget']);
        $this->assertEquals(-150, $report['net_profit']['actual']);
    }

    public function test_a_partial_month_counts_for_its_share_of_days_and_a_range_spans_months_and_years(): void
    {
        $this->actingAs($this->user);
        app(BudgetService::class)->save(2025, [$this->line($this->rent, [12 => 310])]);
        app(BudgetService::class)->save(2026, [$this->line($this->rent, [1 => 310])]);

        $service = app(BudgetService::class);

        $this->assertEquals(160, $service->budgetByAccount('2026-01-16', '2026-01-31')[$this->rent->id]['budget']);
        $this->assertEquals(620, $service->budgetByAccount('2025-12-01', '2026-01-31')[$this->rent->id]['budget']);
        $this->assertEquals(310, $service->budgetByAccount('2025-12-16', '2026-01-15')[$this->rent->id]['budget']);
    }

    public function test_cost_center_budgets_can_be_filtered_and_added_up(): void
    {
        $this->actingAs($this->user);
        $center = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Cairo', 'type' => 'cost']);

        $service = app(BudgetService::class);
        $service->save(2026, [
            $this->line($this->rent, [1 => 60], $center->id),
            $this->line($this->rent, [1 => 40]),
        ]);

        $this->assertEquals(100, $service->budgetByAccount('2026-01-01', '2026-01-31')[$this->rent->id]['budget']);
        $this->assertEquals(60, $service->budgetByAccount('2026-01-01', '2026-01-31', $center->id)[$this->rent->id]['budget']);
        $this->assertEquals(40, $service->budgetByAccount('2026-01-01', '2026-01-31', 'unassigned')[$this->rent->id]['budget']);

        $this->spend('2026-01-05', 70, $center->id);
        $cairo = $service->budgetVsActual('2026-01-01', '2026-01-31', $center->id);
        $this->assertEquals(70, $cairo['total_expenses']['actual']);
        $this->assertEquals(60, $cairo['total_expenses']['budget']);
    }

    public function test_saving_replaces_the_year_and_merges_a_duplicate_account_line(): void
    {
        $this->actingAs($this->user);

        $this->put(route('budgets.update', 2026), ['lines' => [
            $this->line($this->rent, [1 => 100]),
            $this->line($this->rent, [1 => 20, 2 => 5]),
        ]])->assertSessionHasNoErrors();

        $budget = Budget::with('lines')->where('year', 2026)->firstOrFail();
        $this->assertCount(1, $budget->lines);
        $this->assertEquals(120, $budget->lines->first()->m1);
        $this->assertEquals(5, $budget->lines->first()->m2);

        $this->put(route('budgets.update', 2026), ['lines' => []])->assertSessionHasNoErrors();
        $this->assertCount(0, $budget->fresh()->lines);
    }

    public function test_only_revenue_and_expense_accounts_with_non_negative_amounts_can_be_budgeted(): void
    {
        $this->actingAs($this->user)
            ->put(route('budgets.update', 2026), ['lines' => [$this->line($this->bank, [1 => 100])]])
            ->assertSessionHasErrors('lines.0.account_id');

        $this->actingAs($this->user)
            ->put(route('budgets.update', 2026), ['lines' => [$this->line($this->rent, [1 => -5])]])
            ->assertSessionHasErrors('lines.0.months.1');

        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_the_pages_load_and_a_viewer_cannot_change_a_budget(): void
    {
        $this->actingAs($this->user)->get(route('budgets.index'))->assertOk();
        $this->actingAs($this->user)->get(route('budgets.edit', 2026))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/Budgets/Edit')->where('budget.year', 2026));
        $this->actingAs($this->user)->get(route('reports.budget-vs-actual'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/Reports/BudgetVsActual'));

        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)->get(route('budgets.index'))->assertOk();
        $this->actingAs($viewer)->put(route('budgets.update', 2026), ['lines' => []])->assertForbidden();
        $this->actingAs($viewer)->delete(route('budgets.destroy', 2026))->assertForbidden();
    }

    public function test_the_profit_and_loss_can_carry_budget_and_variance_columns(): void
    {
        $this->actingAs($this->user);
        app(BudgetService::class)->save(2026, [$this->line($this->rent, [1 => 100]), $this->line($this->sales, [1 => 300])]);
        $this->spend('2026-01-10', 130);

        $report = app(BudgetService::class)->annotateProfitAndLoss(
            app(ReportService::class)->profitAndLoss('2026-01-01', '2026-01-31'),
        );

        $rent = collect($report['expenses'])->firstWhere('account_id', $this->rent->id);
        $this->assertEquals(100, $rent['budget']);
        $this->assertEquals(-30, $rent['variance']);

        // A budgeted account with no activity yet still appears.
        $this->assertEquals(300, collect($report['revenue'])->firstWhere('account_id', $this->sales->id)['budget']);
        $this->assertEquals(200, $report['net_profit']['budget']);
    }
}
