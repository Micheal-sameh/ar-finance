<?php

namespace Tests\Feature;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\AccountType;
use App\Enums\JournalSourceType;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\Services\JournalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitAndLossAndBalanceSheetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $cash;

    private Account $revenue;

    private Account $expense;

    private Account $equity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->cash = $this->account('1000', 'Cash', AccountType::Asset, 'debit');
        $this->revenue = $this->account('4000', 'Sales Revenue', AccountType::Revenue, 'credit');
        $this->expense = $this->account('6000', 'Rent Expense', AccountType::Expense, 'debit');
        $this->equity = $this->account('3000', "Owner's Equity", AccountType::Equity, 'credit');
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

    private function postManualEntry(string $date, array $lines): void
    {
        app(JournalService::class)->postJournalEntry(new CreateJournalEntryData(
            date: $date,
            description: 'Test entry',
            reference: null,
            sourceType: JournalSourceType::Manual,
            sourceId: null,
            createdBy: $this->user->id,
            lines: array_map(fn ($line) => new JournalLineData(...$line), $lines),
        ));
    }

    public function test_profit_and_loss_nets_revenue_and_expense_for_the_period(): void
    {
        auth()->login($this->user);

        // 1,000 owner investment (balance sheet only, no P&L impact)
        $this->postManualEntry('2026-01-05', [
            ['accountId' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['accountId' => $this->equity->id, 'debit' => 0, 'credit' => 1000],
        ]);

        // 500 revenue
        $this->postManualEntry('2026-01-10', [
            ['accountId' => $this->cash->id, 'debit' => 500, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => 500],
        ]);

        // 200 rent expense
        $this->postManualEntry('2026-01-15', [
            ['accountId' => $this->expense->id, 'debit' => 200, 'credit' => 0],
            ['accountId' => $this->cash->id, 'debit' => 0, 'credit' => 200],
        ]);

        $response = $this->actingAs($this->user)->get(
            route('reports.profit-and-loss', ['from' => '2026-01-01', 'to' => '2026-01-31']),
        );

        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Reports/ProfitAndLoss')
            ->where('report.total_revenue.current', 500)
            ->where('report.total_expenses.current', 200)
            ->where('report.net_profit.current', 300)
        );
    }

    public function test_balance_sheet_balances_and_includes_computed_retained_earnings(): void
    {
        auth()->login($this->user);

        $this->postManualEntry('2026-01-05', [
            ['accountId' => $this->cash->id, 'debit' => 1000, 'credit' => 0],
            ['accountId' => $this->equity->id, 'debit' => 0, 'credit' => 1000],
        ]);
        $this->postManualEntry('2026-01-10', [
            ['accountId' => $this->cash->id, 'debit' => 500, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => 500],
        ]);
        $this->postManualEntry('2026-01-15', [
            ['accountId' => $this->expense->id, 'debit' => 200, 'credit' => 0],
            ['accountId' => $this->cash->id, 'debit' => 0, 'credit' => 200],
        ]);

        $response = $this->actingAs($this->user)->get(
            route('reports.balance-sheet', ['as_of' => '2026-01-31']),
        );

        // Cash = 1000 + 500 - 200 = 1300 (all in Assets)
        // Equity = 1000 (owner) + 300 (retained earnings: 500 revenue - 200 expense) = 1300
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Reports/BalanceSheet')
            ->where('report.total_assets', 1300)
            ->where('report.total_liabilities', 0)
            ->where('report.total_equity', 1300)
            ->where('report.is_balanced', true)
        );
    }

    public function test_profit_and_loss_period_filter_excludes_activity_outside_the_range(): void
    {
        auth()->login($this->user);

        $this->postManualEntry('2026-01-10', [
            ['accountId' => $this->cash->id, 'debit' => 500, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => 500],
        ]);
        $this->postManualEntry('2026-02-10', [
            ['accountId' => $this->cash->id, 'debit' => 300, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => 300],
        ]);

        $response = $this->actingAs($this->user)->get(
            route('reports.profit-and-loss', ['from' => '2026-01-01', 'to' => '2026-01-31']),
        );

        $response->assertInertia(fn ($page) => $page
            ->where('report.total_revenue.current', 500)
        );
    }
}
