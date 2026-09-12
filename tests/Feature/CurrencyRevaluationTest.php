<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyRevaluationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Account $ar;

    private Account $revenue;

    private Account $fxGainLoss;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co', 'base_currency' => 'EGP']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->ar = $this->account('1100', 'Accounts Receivable', AccountType::Asset, 'debit');
        $this->revenue = $this->account('4000', 'Sales Revenue', AccountType::Revenue, 'credit');
        $this->fxGainLoss = $this->account('7900', 'FX Gain/Loss', AccountType::Expense, 'debit');

        $this->client = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Co', 'currency' => 'EUR']);
    }

    private function account(string $code, string $name, AccountType $type, string $normalBalance): Account
    {
        return Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'is_active' => true,
        ]);
    }

    /** Booked at 1.10 (AR debited 1100 for €1000), fakes today's rate at ~1.20. */
    private function fakeCurrentRateOf(float $rate): void
    {
        Http::fake(['*' => Http::response([
            'date' => now()->toDateString(),
            'egp' => ['eur' => round(1 / $rate, 8)],
        ], 200)]);
    }

    private function sendForeignInvoice(string $number = 'INV-EUR-1'): int
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'client_id' => $this->client->id,
            'invoice_number' => $number,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'EUR',
            'exchange_rate' => 1.10,
            'receivable_account_id' => $this->ar->id,
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0, 'account_id' => $this->revenue->id],
            ],
        ])->assertSessionHasNoErrors();

        $invoiceId = Invoice::where('invoice_number', $number)->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('invoices.send', $invoiceId))->assertSessionHasNoErrors();

        return $invoiceId;
    }

    public function test_preview_shows_an_unrealized_gain_when_the_rate_moved_up(): void
    {
        $this->sendForeignInvoice();
        $this->fakeCurrentRateOf(1.20);

        $response = $this->actingAs($this->user)->get(route('revaluation.index', ['date' => now()->toDateString()]));

        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Revaluation/Index')
            ->where('preview.base_currency', 'EGP')
        );

        $rows = $response->viewData('page')['props']['preview']['rows'];
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(1.10, $rows[0]['old_rate'], 0.001);
        $this->assertEqualsWithDelta(1.20, $rows[0]['new_rate'], 0.001);
        $this->assertEqualsWithDelta(1100, $rows[0]['old_base_value'], 0.01);
        $this->assertEqualsWithDelta(1200, $rows[0]['new_base_value'], 0.01);
        $this->assertEqualsWithDelta(100, $rows[0]['unrealized_gain_loss'], 0.01);
    }

    public function test_running_revaluation_posts_the_unrealized_gain_and_updates_the_invoice(): void
    {
        $invoiceId = $this->sendForeignInvoice();
        $this->fakeCurrentRateOf(1.20);

        $response = $this->actingAs($this->user)->post(route('revaluation.revalue'), [
            'date' => now()->toDateString(),
            'fx_gain_loss_account_id' => $this->fxGainLoss->id,
        ]);

        $response->assertSessionHas('success');

        // Receivable moves up by ~100 (gain), credited to the FX account.
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->ar->id, 'debit' => 100]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->fxGainLoss->id, 'credit' => 100]);

        $invoice = Invoice::find($invoiceId);
        $this->assertEqualsWithDelta(1.20, (float) $invoice->revalued_exchange_rate, 0.001);
    }

    public function test_a_second_run_at_the_same_rate_finds_nothing_left_to_revalue(): void
    {
        $this->sendForeignInvoice();
        $this->fakeCurrentRateOf(1.20);

        $this->actingAs($this->user)->post(route('revaluation.revalue'), [
            'date' => now()->toDateString(),
            'fx_gain_loss_account_id' => $this->fxGainLoss->id,
        ]);

        // Rate hasn't moved since the first run — nothing incremental to post.
        $response = $this->actingAs($this->user)->get(route('revaluation.index', ['date' => now()->toDateString()]));

        $this->assertCount(0, $response->viewData('page')['props']['preview']['rows']);
    }

    public function test_paying_after_revaluation_settles_against_the_revalued_rate(): void
    {
        $invoiceId = $this->sendForeignInvoice();
        $this->fakeCurrentRateOf(1.20);

        $this->actingAs($this->user)->post(route('revaluation.revalue'), [
            'date' => now()->toDateString(),
            'fx_gain_loss_account_id' => $this->fxGainLoss->id,
        ]);

        // Settling at exactly the revalued rate (1.20) should need no further
        // FX line — the receivable is already booked at 1.20 (1200).
        $bank = $this->account('1000', 'Bank', AccountType::Asset, 'debit');

        $response = $this->actingAs($this->user)->post(route('invoices.record-payment', $invoiceId), [
            'payment_account_id' => $bank->id,
            'settlement_exchange_rate' => 1.20,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('journal_lines', ['account_id' => $bank->id, 'debit' => 1200]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->ar->id, 'credit' => 1200]);
    }

    public function test_base_currency_invoices_are_never_candidates(): void
    {
        $egpClient = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Local Co', 'currency' => 'EGP']);

        $this->actingAs($this->user)->post(route('invoices.store'), [
            'client_id' => $egpClient->id,
            'invoice_number' => 'INV-EGP-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'receivable_account_id' => $this->ar->id,
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0, 'account_id' => $this->revenue->id],
            ],
        ]);
        $invoiceId = Invoice::where('invoice_number', 'INV-EGP-1')->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('invoices.send', $invoiceId));

        $response = $this->actingAs($this->user)->get(route('revaluation.index', ['date' => now()->toDateString()]));

        $this->assertCount(0, $response->viewData('page')['props']['preview']['rows']);
    }

    public function test_viewer_can_preview_but_not_run_revaluation(): void
    {
        $this->sendForeignInvoice();
        $this->fakeCurrentRateOf(1.20);

        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)->get(route('revaluation.index'))->assertOk();

        $this->actingAs($viewer)->post(route('revaluation.revalue'), [
            'date' => now()->toDateString(),
            'fx_gain_loss_account_id' => $this->fxGainLoss->id,
        ])->assertForbidden();
    }
}
