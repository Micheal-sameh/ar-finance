<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatAndMultiCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $ar;

    private Account $revenue;

    private Account $vatPayable;

    private Account $bank;

    private Account $fxGainLoss;

    private Account $ap;

    private Account $expense;

    private Account $vatReceivable;

    private Client $client;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->ar = $this->account('1100', 'Accounts Receivable', AccountType::Asset, 'debit');
        $this->revenue = $this->account('4000', 'Sales Revenue', AccountType::Revenue, 'credit');
        $this->vatPayable = $this->account('2200', 'VAT Payable', AccountType::Liability, 'credit');
        $this->bank = $this->account('1000', 'Bank', AccountType::Asset, 'debit');
        $this->fxGainLoss = $this->account('7900', 'FX Gain/Loss', AccountType::Expense, 'debit');
        $this->ap = $this->account('2000', 'Accounts Payable', AccountType::Liability, 'credit');
        $this->expense = $this->account('6000', 'Supplies Expense', AccountType::Expense, 'debit');
        $this->vatReceivable = $this->account('1200', 'VAT Receivable', AccountType::Asset, 'debit');

        $this->client = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Co', 'currency' => 'USD']);
        $this->vendor = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Office Supplies Ltd']);
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

    public function test_sending_an_invoice_with_tax_splits_revenue_and_vat_payable(): void
    {
        $response = $this->actingAs($this->user)->post(route('invoices.store'), [
            'client_id' => $this->client->id,
            'invoice_number' => 'INV-0001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
            'receivable_account_id' => $this->ar->id,
            'tax_payable_account_id' => $this->vatPayable->id,
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 15, 'account_id' => $this->revenue->id],
            ],
        ]);
        $response->assertSessionHasNoErrors();

        $invoiceId = \App\Models\Invoice::where('invoice_number', 'INV-0001')->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('invoices.send', $invoiceId))->assertSessionHasNoErrors();

        // 100 subtotal, 15 tax, 115 total
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->ar->id, 'debit' => 115]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->revenue->id, 'credit' => 100]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->vatPayable->id, 'credit' => 15]);
    }

    public function test_sending_a_taxed_invoice_without_a_tax_account_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->post(route('invoices.store'), [
            'client_id' => $this->client->id,
            'invoice_number' => 'INV-0002',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
            'receivable_account_id' => $this->ar->id,
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 15, 'account_id' => $this->revenue->id],
            ],
        ]);

        $response->assertSessionHasErrors('tax_payable_account_id');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_foreign_currency_invoice_posts_in_base_currency_using_exchange_rate(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'client_id' => $this->client->id,
            'invoice_number' => 'INV-EUR-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'EUR',
            'exchange_rate' => 1.10,
            'receivable_account_id' => $this->ar->id,
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0, 'account_id' => $this->revenue->id],
            ],
        ])->assertSessionHasNoErrors();
        $invoiceId = \App\Models\Invoice::where('invoice_number', 'INV-EUR-1')->firstOrFail()->id;

        $this->actingAs($this->user)->post(route('invoices.send', $invoiceId))->assertSessionHasNoErrors();

        // €1000 * 1.10 = $1100 posted to the GL, not the €1000 face value.
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->ar->id, 'debit' => 1100]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->revenue->id, 'credit' => 1100]);
    }

    public function test_settling_at_a_different_rate_posts_a_realized_fx_gain(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'client_id' => $this->client->id,
            'invoice_number' => 'INV-EUR-2',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'EUR',
            'exchange_rate' => 1.10,
            'receivable_account_id' => $this->ar->id,
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0, 'account_id' => $this->revenue->id],
            ],
        ]);
        $invoiceId = \App\Models\Invoice::where('invoice_number', 'INV-EUR-2')->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('invoices.send', $invoiceId));

        // Booked at 1.10 ($1100); euro strengthens to 1.15 by payment time ($1150).
        $response = $this->actingAs($this->user)->post(route('invoices.record-payment', $invoiceId), [
            'payment_account_id' => $this->bank->id,
            'settlement_exchange_rate' => 1.15,
            'fx_gain_loss_account_id' => $this->fxGainLoss->id,
        ]);
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bank->id, 'debit' => 1150]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->ar->id, 'credit' => 1100]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->fxGainLoss->id, 'credit' => 50]);
    }

    public function test_settling_at_a_different_rate_without_an_fx_account_is_rejected(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'client_id' => $this->client->id,
            'invoice_number' => 'INV-EUR-3',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'EUR',
            'exchange_rate' => 1.10,
            'receivable_account_id' => $this->ar->id,
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0, 'account_id' => $this->revenue->id],
            ],
        ]);
        $invoiceId = \App\Models\Invoice::where('invoice_number', 'INV-EUR-3')->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('invoices.send', $invoiceId));

        $response = $this->actingAs($this->user)->post(route('invoices.record-payment', $invoiceId), [
            'payment_account_id' => $this->bank->id,
            'settlement_exchange_rate' => 1.15,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'sent']);
    }

    public function test_approving_a_bill_with_tax_splits_expense_and_input_vat(): void
    {
        $response = $this->actingAs($this->user)->post(route('bills.store'), [
            'vendor_id' => $this->vendor->id,
            'bill_number' => 'BILL-0001',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'payable_account_id' => $this->ap->id,
            'tax_receivable_account_id' => $this->vatReceivable->id,
            'lines' => [
                ['description' => 'Supplies', 'quantity' => 1, 'unit_price' => 200, 'tax_rate' => 15, 'account_id' => $this->expense->id],
            ],
        ]);
        $response->assertSessionHasNoErrors();
        $billId = \App\Models\Bill::where('bill_number', 'BILL-0001')->firstOrFail()->id;

        $this->actingAs($this->user)->post(route('bills.approve', $billId))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->expense->id, 'debit' => 200]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->vatReceivable->id, 'debit' => 30]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->ap->id, 'credit' => 230]);
    }

    public function test_vat_return_nets_output_and_input_vat(): void
    {
        // Sale: 100 subtotal, 15 VAT.
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'client_id' => $this->client->id,
            'invoice_number' => 'INV-0010',
            'issue_date' => '2026-02-05',
            'due_date' => '2026-03-05',
            'currency' => 'USD',
            'receivable_account_id' => $this->ar->id,
            'tax_payable_account_id' => $this->vatPayable->id,
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 15, 'account_id' => $this->revenue->id],
            ],
        ]);
        $invoiceId = \App\Models\Invoice::where('invoice_number', 'INV-0010')->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('invoices.send', $invoiceId));

        // Purchase: 40 subtotal, 6 VAT.
        $this->actingAs($this->user)->post(route('bills.store'), [
            'vendor_id' => $this->vendor->id,
            'bill_number' => 'BILL-0010',
            'bill_date' => '2026-02-10',
            'due_date' => '2026-03-10',
            'payable_account_id' => $this->ap->id,
            'tax_receivable_account_id' => $this->vatReceivable->id,
            'lines' => [
                ['description' => 'Supplies', 'quantity' => 1, 'unit_price' => 40, 'tax_rate' => 15, 'account_id' => $this->expense->id],
            ],
        ]);
        $billId = \App\Models\Bill::where('bill_number', 'BILL-0010')->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('bills.approve', $billId));

        $response = $this->actingAs($this->user)->get(route('reports.vat-return', ['from' => '2026-02-01', 'to' => '2026-02-28']));

        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Reports/VatReturn')
            ->where('report.output_vat', 15)
            ->where('report.input_vat', 6)
            ->where('report.net_vat_payable', 9)
        );
    }
}
