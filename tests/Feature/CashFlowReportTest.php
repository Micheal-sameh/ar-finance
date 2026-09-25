<?php

namespace Tests\Feature;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\AccountType;
use App\Enums\DepreciationMethod;
use App\Enums\JournalSourceType;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\FixedAsset;
use App\Models\Tenant;
use App\Models\User;
use App\Services\JournalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $cash;

    private Account $ar;

    private Account $ap;

    private Account $equipment;

    private Account $accumulatedDepreciation;

    private Account $depreciationExpense;

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
        $this->ar = $this->account('1200', 'Accounts Receivable', AccountType::Asset, 'debit');
        $this->ap = $this->account('2000', 'Accounts Payable', AccountType::Liability, 'credit');
        $this->equipment = $this->account('1500', 'Office Equipment', AccountType::Asset, 'debit');
        $this->accumulatedDepreciation = $this->account('1510', 'Accumulated Depreciation', AccountType::Asset, 'credit');
        $this->depreciationExpense = $this->account('6200', 'Depreciation Expense', AccountType::Expense, 'debit');
        $this->revenue = $this->account('4000', 'Sales Revenue', AccountType::Revenue, 'credit');
        $this->expense = $this->account('5000', 'Office Expense', AccountType::Expense, 'debit');
        $this->equity = $this->account('3000', "Owner's Equity", AccountType::Equity, 'credit');

        BankAccount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Account',
            'account_id' => $this->cash->id,
        ]);

        FixedAsset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Office Equipment',
            'purchase_date' => '2026-01-01',
            'cost' => 1200,
            'salvage_value' => 0,
            'useful_life_years' => 5,
            'depreciation_method' => DepreciationMethod::StraightLine,
            'asset_account_id' => $this->equipment->id,
            'depreciation_account_id' => $this->depreciationExpense->id,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciation->id,
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

    public function test_cash_flow_statement_reconciles_operating_investing_and_financing(): void
    {
        auth()->login($this->user);

        // Owner contributes cash — Financing
        $this->postManualEntry('2026-01-02', [
            ['accountId' => $this->cash->id, 'debit' => 5000, 'credit' => 0],
            ['accountId' => $this->equity->id, 'debit' => 0, 'credit' => 5000],
        ]);

        // Buy equipment for cash — Investing
        $this->postManualEntry('2026-01-05', [
            ['accountId' => $this->equipment->id, 'debit' => 1200, 'credit' => 0],
            ['accountId' => $this->cash->id, 'debit' => 0, 'credit' => 1200],
        ]);

        // Sale on credit — Operating (net income + AR working capital)
        $this->postManualEntry('2026-01-10', [
            ['accountId' => $this->ar->id, 'debit' => 800, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => 800],
        ]);

        // Collect part of the receivable
        $this->postManualEntry('2026-01-15', [
            ['accountId' => $this->cash->id, 'debit' => 300, 'credit' => 0],
            ['accountId' => $this->ar->id, 'debit' => 0, 'credit' => 300],
        ]);

        // Expense incurred on credit — Operating (net income + AP working capital)
        $this->postManualEntry('2026-01-20', [
            ['accountId' => $this->expense->id, 'debit' => 250, 'credit' => 0],
            ['accountId' => $this->ap->id, 'debit' => 0, 'credit' => 250],
        ]);

        // Depreciation — non-cash, added back to Operating
        $this->postManualEntry('2026-01-25', [
            ['accountId' => $this->depreciationExpense->id, 'debit' => 100, 'credit' => 0],
            ['accountId' => $this->accumulatedDepreciation->id, 'debit' => 0, 'credit' => 100],
        ]);

        $response = $this->actingAs($this->user)->get(
            route('reports.cash-flow', ['from' => '2026-01-01', 'to' => '2026-01-31']),
        );

        // Net income = 800 revenue - 250 expense - 100 depreciation = 450
        // Operating = 450 + 100 depreciation add-back - 500 AR increase + 250 AP increase = 300
        // Investing = -1200 (equipment purchase)
        // Financing = +5000 (owner contribution)
        // Net change in cash = 300 - 1200 + 5000 = 4100, matching actual cash movement (5000 - 1200 + 300)
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Reports/CashFlow')
            ->where('report.operating.net_income', 450)
            ->where('report.operating.total', 300)
            ->where('report.investing.total', -1200)
            ->where('report.financing.total', 5000)
            ->where('report.net_change_in_cash', 4100)
            ->where('report.beginning_cash', 0)
            ->where('report.ending_cash', 4100)
            ->where('report.is_reconciled', true)
        );
    }
}
