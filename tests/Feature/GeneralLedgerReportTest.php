<?php

namespace Tests\Feature;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\AccountType;
use App\Enums\CostCenterType;
use App\Enums\JournalSourceType;
use App\Models\Account;
use App\Models\CostCenter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\JournalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralLedgerReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $cash;

    private Account $revenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->cash = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1000',
            'name' => 'Cash',
            'type' => AccountType::Asset,
            'normal_balance' => 'debit',
        ]);
        $this->revenue = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '4000',
            'name' => 'Sales Revenue',
            'type' => AccountType::Revenue,
            'normal_balance' => 'credit',
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

    public function test_general_ledger_sorts_multiple_lines_by_date_without_error(): void
    {
        auth()->login($this->user);

        $this->postManualEntry('2026-01-10', [
            ['accountId' => $this->cash->id, 'debit' => 500, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => 500],
        ]);
        $this->postManualEntry('2026-01-05', [
            ['accountId' => $this->cash->id, 'debit' => 300, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => 300],
        ]);
        $this->postManualEntry('2026-01-20', [
            ['accountId' => $this->cash->id, 'debit' => 100, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => 100],
        ]);

        $response = $this->actingAs($this->user)->get(
            route('reports.general-ledger', ['account_id' => $this->cash->id, 'from' => '2026-01-01', 'to' => '2026-01-31']),
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Reports/GeneralLedger')
            ->where('ledger.lines.0.debit', 300)
            ->where('ledger.lines.1.debit', 500)
            ->where('ledger.lines.2.debit', 100)
            ->where('ledger.ending_balance', 900)
        );
    }

    public function test_general_ledger_can_be_filtered_to_a_cost_center(): void
    {
        auth()->login($this->user);

        $marketing = CostCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Marketing',
            'type' => CostCenterType::Cost,
        ]);

        $this->postManualEntry('2026-01-10', [
            ['accountId' => $this->cash->id, 'debit' => 500, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => 500, 'costCenterId' => $marketing->id],
        ]);
        $this->postManualEntry('2026-01-12', [
            ['accountId' => $this->cash->id, 'debit' => 300, 'credit' => 0],
            ['accountId' => $this->revenue->id, 'debit' => 0, 'credit' => 300],
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.general-ledger', [
            'account_id' => $this->revenue->id,
            'from' => '2026-01-01',
            'to' => '2026-01-31',
            'cost_center_id' => $marketing->id,
        ]));

        $response->assertInertia(fn ($page) => $page
            ->where('costCenterLabel', 'Marketing')
            ->where('ledger.lines.0.credit', 500)
            ->where('ledger.ending_balance', 500)
        );
    }
}
