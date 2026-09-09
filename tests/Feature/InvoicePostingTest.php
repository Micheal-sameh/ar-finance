<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePostingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $ar;

    private Account $revenue;

    private Account $bank;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->ar = $this->account('1100', 'Accounts Receivable', AccountType::Asset, 'debit');
        $this->revenue = $this->account('4000', 'Sales Revenue', AccountType::Revenue, 'credit');
        $this->bank = $this->account('1000', 'Bank', AccountType::Asset, 'debit');

        $this->client = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Co', 'currency' => 'USD']);
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

    private function createDraftInvoice(array $overrides = []): int
    {
        $response = $this->actingAs($this->user)->post(route('invoices.store'), array_merge([
            'client_id' => $this->client->id,
            'invoice_number' => 'INV-0001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
            'receivable_account_id' => $this->ar->id,
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 0, 'account_id' => $this->revenue->id],
            ],
        ], $overrides));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        return \App\Models\Invoice::where('invoice_number', $overrides['invoice_number'] ?? 'INV-0001')->firstOrFail()->id;
    }

    public function test_draft_invoice_does_not_post_to_the_ledger(): void
    {
        $invoiceId = $this->createDraftInvoice();

        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'draft']);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_sending_an_invoice_posts_a_balanced_journal_entry(): void
    {
        $invoiceId = $this->createDraftInvoice();

        $response = $this->actingAs($this->user)->post(route('invoices.send', $invoiceId));
        $response->assertRedirect();

        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'sent']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->ar->id, 'debit' => 200]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->revenue->id, 'credit' => 200]);
    }

    public function test_recording_payment_posts_cash_receipt_and_marks_paid(): void
    {
        $invoiceId = $this->createDraftInvoice();
        $this->actingAs($this->user)->post(route('invoices.send', $invoiceId));

        $response = $this->actingAs($this->user)->post(route('invoices.record-payment', $invoiceId), [
            'payment_account_id' => $this->bank->id,
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'paid']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bank->id, 'debit' => 200]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->ar->id, 'credit' => 200]);

        // Two journal entries now exist (send + payment) and the GL as a
        // whole still balances.
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_cannot_send_an_already_sent_invoice(): void
    {
        $invoiceId = $this->createDraftInvoice();
        $this->actingAs($this->user)->post(route('invoices.send', $invoiceId));

        $response = $this->actingAs($this->user)->post(route('invoices.send', $invoiceId));

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_draft_invoice_can_be_voided_without_touching_the_ledger(): void
    {
        $invoiceId = $this->createDraftInvoice();

        $response = $this->actingAs($this->user)->post(route('invoices.void', $invoiceId));
        $response->assertRedirect();

        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'status' => 'void']);
        $this->assertDatabaseCount('journal_entries', 0);
    }
}
