<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Client;
use App\Models\JournalEntry;
use App\Models\ReceivedPayment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivedPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $bank;

    private Account $deposits;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->bank = $this->account('1000', 'Bank', AccountType::Asset, 'debit');
        $this->deposits = $this->account('2200', 'Customer Deposits', AccountType::Liability, 'credit');
    }

    private function account(string $code, string $name, AccountType $type, string $normalBalance, ?Tenant $tenant = null): Account
    {
        return Account::create([
            'tenant_id' => ($tenant ?? $this->tenant)->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'description' => 'Advance for March event',
            'amount' => 250,
            'received_on' => '2026-01-15',
            'deposit_account_id' => $this->bank->id,
            'credit_account_id' => $this->deposits->id,
            ...$overrides,
        ];
    }

    public function test_receiving_a_payment_without_an_invoice_posts_a_balanced_journal_entry(): void
    {
        $client = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme']);

        $this->actingAs($this->user)
            ->post(route('received-payments.store'), $this->payload(['client_id' => $client->id, 'reference' => 'TRF-1']))
            ->assertRedirect(route('received-payments.index'));

        $payment = ReceivedPayment::firstOrFail();
        $this->assertSame($client->id, $payment->client_id);
        $this->assertSame('250.00', $payment->amount);

        $entry = JournalEntry::with('lines')->findOrFail($payment->journal_entry_id);
        $this->assertSame('received_payment', $entry->source_type->value);
        $this->assertEquals(250, $entry->lines->firstWhere('account_id', $this->bank->id)->debit);
        $this->assertEquals(250, $entry->lines->firstWhere('account_id', $this->deposits->id)->credit);
    }

    public function test_the_deposit_and_credit_accounts_must_differ_and_belong_to_the_tenant(): void
    {
        $other = Tenant::create(['name' => 'Other Co', 'slug' => 'other-co']);
        $foreign = $this->account('1000', 'Foreign Bank', AccountType::Asset, 'debit', $other);

        $this->actingAs($this->user)
            ->post(route('received-payments.store'), $this->payload(['credit_account_id' => $this->bank->id]))
            ->assertSessionHasErrors('credit_account_id');

        $this->actingAs($this->user)
            ->post(route('received-payments.store'), $this->payload(['deposit_account_id' => $foreign->id]))
            ->assertSessionHasErrors('deposit_account_id');

        $this->assertDatabaseCount('received_payments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_the_amount_must_be_positive_and_not_dated_in_the_future(): void
    {
        $this->actingAs($this->user)
            ->post(route('received-payments.store'), $this->payload(['amount' => 0, 'received_on' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['amount', 'received_on']);

        $this->assertDatabaseCount('received_payments', 0);
    }

    public function test_a_viewer_can_list_but_not_record_received_payments(): void
    {
        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)->get(route('received-payments.index'))->assertOk();
        $this->actingAs($viewer)->post(route('received-payments.store'), $this->payload())->assertForbidden();
    }

    public function test_platform_admin_picks_a_service_then_records_a_received_payment_for_it(): void
    {
        $admin = User::factory()->create(['tenant_id' => null]);
        $admin->assignRole('Platform Admin');

        $this->actingAs($admin)
            ->get(route('received-payments.create'))
            ->assertInertia(fn ($page) => $page->component('Platform/ChooseService'));

        $this->actingAs($admin)
            ->get(route('received-payments.create', ['for_tenant' => $this->tenant->id]))
            ->assertInertia(fn ($page) => $page->component('Sales/ReceivedPayments/Create'));

        $this->actingAs($admin)
            ->post(route('received-payments.store', ['for_tenant' => $this->tenant->id]), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame($this->tenant->id, ReceivedPayment::firstOrFail()->tenant_id);
    }
}
