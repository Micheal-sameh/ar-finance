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

class ReportPdfExportTest extends TestCase
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

        auth()->login($this->user);

        app(JournalService::class)->postJournalEntry(new CreateJournalEntryData(
            date: '2026-01-05',
            description: 'Owner investment',
            reference: null,
            sourceType: JournalSourceType::Manual,
            sourceId: null,
            createdBy: $this->user->id,
            lines: [
                new JournalLineData(accountId: $this->cash->id, debit: 1000, credit: 0),
                new JournalLineData(accountId: $this->equity->id, debit: 0, credit: 1000),
            ],
        ));
        app(JournalService::class)->postJournalEntry(new CreateJournalEntryData(
            date: '2026-01-10',
            description: 'Revenue',
            reference: null,
            sourceType: JournalSourceType::Manual,
            sourceId: null,
            createdBy: $this->user->id,
            lines: [
                new JournalLineData(accountId: $this->cash->id, debit: 500, credit: 0),
                new JournalLineData(accountId: $this->revenue->id, debit: 0, credit: 500),
            ],
        ));
        app(JournalService::class)->postJournalEntry(new CreateJournalEntryData(
            date: '2026-01-15',
            description: 'Rent',
            reference: null,
            sourceType: JournalSourceType::Manual,
            sourceId: null,
            createdBy: $this->user->id,
            lines: [
                new JournalLineData(accountId: $this->expense->id, debit: 200, credit: 0),
                new JournalLineData(accountId: $this->cash->id, debit: 0, credit: 200),
            ],
        ));
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

    public function test_trial_balance_pdf_route_streams_a_pdf(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.trial-balance.pdf', ['from' => '2026-01-01', 'to' => '2026-01-31']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_profit_and_loss_pdf_route_streams_a_pdf(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.profit-and-loss.pdf', ['from' => '2026-01-01', 'to' => '2026-01-31']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_balance_sheet_pdf_route_streams_a_pdf(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.balance-sheet.pdf', ['as_of' => '2026-01-31']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_cash_flow_pdf_route_streams_a_pdf(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.cash-flow.pdf', ['from' => '2026-01-01', 'to' => '2026-01-31']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
