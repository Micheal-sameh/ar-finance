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

class PlatformProfitAndLossTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function tenantWithJournalEntry(string $name, float $revenue, float $expense): Tenant
    {
        $tenant = Tenant::create(['name' => $name, 'slug' => str($name)->slug(), 'base_currency' => 'EGP']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('Super Admin');

        $cash = Account::create(['tenant_id' => $tenant->id, 'code' => '1000', 'name' => 'Cash', 'type' => AccountType::Asset, 'normal_balance' => 'debit']);
        $revenueAccount = Account::create(['tenant_id' => $tenant->id, 'code' => '4000', 'name' => 'Sales Revenue', 'type' => AccountType::Revenue, 'normal_balance' => 'credit']);
        $expenseAccount = Account::create(['tenant_id' => $tenant->id, 'code' => '6000', 'name' => 'Rent Expense', 'type' => AccountType::Expense, 'normal_balance' => 'debit']);

        auth()->login($user);

        app(JournalService::class)->postJournalEntry(new CreateJournalEntryData(
            date: '2026-01-10',
            description: 'Revenue',
            reference: null,
            sourceType: JournalSourceType::Manual,
            sourceId: null,
            createdBy: $user->id,
            lines: [
                new JournalLineData(accountId: $cash->id, debit: $revenue, credit: 0),
                new JournalLineData(accountId: $revenueAccount->id, debit: 0, credit: $revenue),
            ],
        ));

        app(JournalService::class)->postJournalEntry(new CreateJournalEntryData(
            date: '2026-01-15',
            description: 'Expense',
            reference: null,
            sourceType: JournalSourceType::Manual,
            sourceId: null,
            createdBy: $user->id,
            lines: [
                new JournalLineData(accountId: $expenseAccount->id, debit: $expense, credit: 0),
                new JournalLineData(accountId: $cash->id, debit: 0, credit: $expense),
            ],
        ));

        auth()->logout();

        return $tenant;
    }

    public function test_platform_profit_and_loss_shows_each_tenants_own_totals(): void
    {
        $tenantA = $this->tenantWithJournalEntry('Alpha Co', revenue: 500, expense: 200);
        $tenantB = $this->tenantWithJournalEntry('Beta Co', revenue: 900, expense: 100);

        $admin = User::factory()->create(['tenant_id' => null]);
        $admin->assignRole('Platform Admin');

        $response = $this->actingAs($admin)->get(
            route('platform.reports.profit-and-loss', ['from' => '2026-01-01', 'to' => '2026-01-31']),
        );

        $response->assertOk();

        $rows = collect($response->viewData('page')['props']['rows']);
        $rowA = $rows->firstWhere('tenant_id', $tenantA->id);
        $rowB = $rows->firstWhere('tenant_id', $tenantB->id);

        $this->assertSame(500.0, $rowA['total_revenue']);
        $this->assertSame(200.0, $rowA['total_expenses']);
        $this->assertSame(300.0, $rowA['net_profit']);

        $this->assertSame(900.0, $rowB['total_revenue']);
        $this->assertSame(100.0, $rowB['total_expenses']);
        $this->assertSame(800.0, $rowB['net_profit']);
    }
}
