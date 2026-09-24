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
use Tests\TestCase;

class ClientHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $ar;

    private Account $revenue;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->ar = Account::create([
            'tenant_id' => $this->tenant->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset, 'normal_balance' => 'debit',
        ]);
        $this->revenue = Account::create([
            'tenant_id' => $this->tenant->id, 'code' => '4000', 'name' => 'Sales Revenue', 'type' => AccountType::Revenue, 'normal_balance' => 'credit',
        ]);

        $this->client = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Co', 'currency' => 'USD']);
    }

    private function invoice(string $number, string $status): Invoice
    {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'invoice_number' => $number,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => $status,
            'currency' => 'USD',
            'exchange_rate' => 1,
            'receivable_account_id' => $this->ar->id,
        ]);
        $invoice->lines()->create([
            'description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0, 'account_id' => $this->revenue->id,
        ]);

        return $invoice;
    }

    public function test_client_show_page_lists_invoice_history_and_summary(): void
    {
        $this->invoice('INV-0001', 'paid');
        $this->invoice('INV-0002', 'sent');
        $this->invoice('INV-0003', 'draft');

        $response = $this->actingAs($this->user)->get(route('clients.show', $this->client->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Contacts/Clients/Show')
            ->where('client.id', $this->client->id)
            ->has('invoices', 3)
            ->where('summary.invoice_count', 3)
            ->where('summary.total_invoiced', 200)
            ->where('summary.total_paid', 100)
            ->where('summary.outstanding', 100)
        );
    }

    public function test_cannot_view_another_tenants_client(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other Co', 'slug' => 'other-co']);
        $otherClient = Client::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Client', 'currency' => 'USD']);

        $response = $this->actingAs($this->user)->get(route('clients.show', $otherClient->id));

        $response->assertNotFound();
    }
}
