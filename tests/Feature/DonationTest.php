<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\GlMappingRole;
use App\Models\Account;
use App\Models\CostCenter;
use App\Models\GlMapping;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Donations\Models\Donation;
use App\Modules\Donations\Models\Donor;
use App\Modules\Donations\Services\DonationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DonationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $agent;

    private User $admin;

    private CostCenter $center;

    private CostCenter $otherCenter;

    private Account $cash;

    private Account $bank;

    private Account $pos;

    private Account $income;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'St. Mary Church', 'slug' => 'st-mary', 'base_currency' => 'EGP', 'modules' => ['donations']]);
        $this->agent = $this->user('Donations Agent');
        $this->admin = $this->user('Donations Admin');

        $this->cash = $this->account('1010', 'Cash on Hand', AccountType::Asset);
        $this->bank = $this->account('1020', 'Bank', AccountType::Asset);
        $this->pos = $this->account('1030', 'POS Clearing', AccountType::Asset);
        $this->income = $this->account('4500', 'Donations Income', AccountType::Revenue);

        $this->center = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Charity', 'type' => 'profit']);
        $this->otherCenter = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Building Fund', 'type' => 'profit']);

        foreach ([
            GlMappingRole::DonationCashPayment->value => $this->cash,
            GlMappingRole::DonationBankPayment->value => $this->bank,
            GlMappingRole::DonationPosPayment->value => $this->pos,
            GlMappingRole::DonationRevenue->value => $this->income,
        ] as $role => $account) {
            GlMapping::create(['tenant_id' => $this->tenant->id, 'role' => $role, 'account_id' => $account->id]);
        }
    }

    private function user(string $role, ?Tenant $tenant = null): User
    {
        $user = User::factory()->create(['tenant_id' => ($tenant ?? $this->tenant)->id]);
        $user->assignRole($role);

        return $user;
    }

    private function account(string $code, string $name, AccountType $type): Account
    {
        return Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $type === AccountType::Asset ? 'debit' : 'credit',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cost_center_id' => $this->center->id,
            'amount' => 250,
            'donated_at' => '2026-11-01',
            'payment_method' => 'cash',
        ], $overrides);
    }

    private function record(array $overrides = [], ?User $as = null): Donation
    {
        $this->actingAs($as ?? $this->agent)->post(route('donations.store'), $this->payload($overrides))->assertSessionHasNoErrors();

        return Donation::query()->latest('id')->firstOrFail();
    }

    public function test_a_donation_is_recorded_and_posted_at_the_chosen_center(): void
    {
        $donor = Donor::create(['tenant_id' => $this->tenant->id, 'name' => 'Mina Samir']);

        $this->actingAs($this->agent)->post(route('donations.store'), $this->payload(['donor_id' => $donor->id, 'payment_reference' => 'RCPT-9']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $donation = Donation::firstOrFail();
        $this->assertSame('received', $donation->status->value);
        $this->assertSame(1, $donation->receipt_number);
        $this->assertSame($this->agent->id, $donation->created_by);
        $this->assertSame('EGP', $donation->currency);
        $this->assertEquals(250.00, (float) $donation->base_amount);

        $this->assertDatabaseHas('journal_entries', ['source_type' => 'donation', 'source_id' => $donation->id, 'reference' => 'RCPT-9']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->cash->id, 'debit' => 250.00, 'cost_center_id' => $this->center->id]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->income->id, 'credit' => 250.00, 'cost_center_id' => $this->center->id]);
    }

    public function test_a_donation_can_be_anonymous(): void
    {
        $donation = $this->record();

        $this->assertNull($donation->donor_id);
    }

    public function test_each_payment_method_posts_to_its_own_account(): void
    {
        $this->record(['payment_method' => 'bank_transfer', 'payment_reference' => 'TRX-1']);
        $this->record(['payment_method' => 'pos', 'payment_reference' => 'POS-1']);

        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bank->id, 'debit' => 250.00]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->pos->id, 'debit' => 250.00]);
        $this->assertDatabaseMissing('journal_lines', ['account_id' => $this->cash->id]);
        $this->assertSame([1, 2], Donation::orderBy('id')->pluck('receipt_number')->all());
    }

    public function test_a_foreign_currency_donation_is_posted_in_the_base_currency(): void
    {
        $donation = $this->record(['amount' => 100, 'currency' => 'usd', 'exchange_rate' => 50]);

        $this->assertSame('USD', $donation->currency);
        $this->assertEquals(100.00, (float) $donation->amount);
        $this->assertEquals(5000.00, (float) $donation->base_amount);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->cash->id, 'debit' => 5000.00]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->income->id, 'credit' => 5000.00]);
    }

    public function test_invalid_donations_are_rejected(): void
    {
        $post = fn (array $overrides) => $this->actingAs($this->agent)->post(route('donations.store'), $this->payload($overrides));

        $post(['amount' => 0])->assertSessionHasErrors('amount');
        $post(['amount' => 'abc'])->assertSessionHasErrors('amount');
        $post(['donated_at' => now()->addDay()->toDateString()])->assertSessionHasErrors('donated_at');
        $post(['cost_center_id' => null])->assertSessionHasErrors('cost_center_id');
        $post(['payment_method' => 'cheque'])->assertSessionHasErrors('payment_method');
        $post(['payment_reference' => str_repeat('x', 101)])->assertSessionHasErrors('payment_reference');
        $post(['currency' => 'EGPT'])->assertSessionHasErrors('currency');
        $post(['exchange_rate' => -1])->assertSessionHasErrors('exchange_rate');

        $this->assertDatabaseCount('donations', 0);
    }

    public function test_a_donation_is_refused_when_its_method_has_no_mapped_account(): void
    {
        GlMapping::where('role', GlMappingRole::DonationPosPayment)->delete();

        $this->actingAs($this->agent)->post(route('donations.store'), $this->payload(['payment_method' => 'pos']))->assertSessionHas('error');
        $this->assertDatabaseCount('donations', 0);
        $this->assertDatabaseCount('journal_entries', 0);

        $this->actingAs($this->agent)->post(route('donations.store'), $this->payload(['payment_method' => 'cash']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('donations', 1);
    }

    public function test_a_donation_is_refused_when_no_income_account_is_mapped(): void
    {
        GlMapping::where('role', GlMappingRole::DonationRevenue)->delete();

        $this->actingAs($this->agent)->post(route('donations.store'), $this->payload())->assertSessionHas('error');
        $this->assertDatabaseCount('donations', 0);
    }

    public function test_a_center_or_donor_from_another_tenant_is_rejected(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'base_currency' => 'EGP', 'modules' => ['donations']]);
        $foreignCenter = CostCenter::create(['tenant_id' => $other->id, 'name' => 'Foreign', 'type' => 'profit']);
        $foreignDonor = Donor::create(['tenant_id' => $other->id, 'name' => 'Stranger']);

        $this->actingAs($this->agent)->post(route('donations.store'), $this->payload(['cost_center_id' => $foreignCenter->id]))->assertSessionHasErrors('cost_center_id');
        $this->actingAs($this->agent)->post(route('donations.store'), $this->payload(['donor_id' => $foreignDonor->id]))->assertSessionHasErrors('donor_id');
        $this->assertDatabaseCount('donations', 0);
    }

    public function test_the_list_shows_who_recorded_each_donation_and_filters_by_center_and_method(): void
    {
        $this->record(['payment_method' => 'cash']);
        $this->record(['payment_method' => 'pos', 'cost_center_id' => $this->otherCenter->id], $this->admin);

        $this->actingAs($this->agent)->get(route('donations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Donations/Index')
                ->has('donations.data', 2)
                ->where('donations.data.0.created_by_name', $this->admin->name)
                ->where('donations.data.1.created_by_name', $this->agent->name)
                ->where('canCreate', true));

        $this->actingAs($this->agent)->get(route('donations.index', ['payment_method' => 'pos']))
            ->assertInertia(fn ($page) => $page->has('donations.data', 1)->where('donations.data.0.cost_center_name', 'Building Fund'));

        $this->actingAs($this->agent)->get(route('donations.index', ['cost_center_id' => $this->center->id]))
            ->assertInertia(fn ($page) => $page->has('donations.data', 1)->where('donations.data.0.payment_method', 'cash'));

        $this->actingAs($this->agent)->get(route('donations.index', ['created_by' => $this->admin->id]))
            ->assertInertia(fn ($page) => $page->has('donations.data', 1));
    }

    public function test_the_list_can_be_filtered_by_currency(): void
    {
        $this->record(['amount' => 100]);
        $this->record(['amount' => 10, 'currency' => 'USD', 'exchange_rate' => 50]);
        $this->record(['amount' => 20, 'currency' => 'USD', 'exchange_rate' => 50]);

        $this->actingAs($this->agent)->get(route('donations.index', ['currency' => 'usd']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('donations.data', 2)->where('donations.data.0.currency', 'USD')->where('filters.currency', 'USD'));

        $this->actingAs($this->agent)->get(route('donations.index', ['currency' => 'EGP']))
            ->assertInertia(fn ($page) => $page->has('donations.data', 1));

        $this->actingAs($this->agent)->get(route('donations.index', ['currency' => 'EGPT']))->assertSessionHasErrors('currency');
        $this->actingAs($this->agent)->get(route('donations.index'))->assertInertia(fn ($page) => $page->has('donations.data', 3));
    }

    public function test_cancelling_reverses_the_journal_at_the_same_accounts_and_center(): void
    {
        $donation = $this->record();

        $this->actingAs($this->admin)->post(route('donations.cancel', $donation))->assertSessionHas('success');

        $donation->refresh();
        $this->assertSame('cancelled', $donation->status->value);
        $this->assertSame($this->admin->id, $donation->cancelled_by);
        $this->assertNotNull($donation->cancelled_at);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->cash->id, 'credit' => 250.00, 'cost_center_id' => $this->center->id]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->income->id, 'debit' => 250.00, 'cost_center_id' => $this->center->id]);
        $this->assertEquals(0.0, (float) DB::table('journal_lines')->where('account_id', $this->income->id)->selectRaw('sum(credit) - sum(debit) as net')->value('net'));

        $this->actingAs($this->admin)->post(route('donations.cancel', $donation))->assertSessionHas('error');
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_an_agent_cannot_cancel_a_donation_or_edit_a_donor(): void
    {
        $donation = $this->record();
        $donor = Donor::create(['tenant_id' => $this->tenant->id, 'name' => 'Mina']);

        $this->actingAs($this->agent)->post(route('donations.cancel', $donation))->assertForbidden();
        $this->actingAs($this->agent)->put(route('donors.update', $donor), ['name' => 'Changed'])->assertForbidden();

        $this->assertSame('received', $donation->fresh()->status->value);
        $this->assertSame('Mina', $donor->fresh()->name);
    }

    public function test_a_viewer_cannot_see_or_record_donations(): void
    {
        $viewer = $this->user('Viewer');

        $this->actingAs($viewer)->get(route('donations.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('donors.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('donations.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('donations', 0);
    }

    public function test_another_tenants_donation_cannot_be_cancelled_or_printed(): void
    {
        $donation = $this->record();
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'base_currency' => 'EGP', 'modules' => ['donations']]);
        $outsider = $this->user('Donations Admin', $other);

        $this->actingAs($outsider)->post(route('donations.cancel', $donation))->assertNotFound();
        $this->actingAs($outsider)->get(route('donations.receipt', $donation))->assertNotFound();
        $this->assertSame('received', $donation->fresh()->status->value);
    }

    public function test_a_donor_needs_only_a_name_and_phone_and_email_are_optional(): void
    {
        $this->actingAs($this->agent)->post(route('donors.store'), ['name' => 'Mina Samir'])->assertSessionHasNoErrors();
        $this->actingAs($this->agent)->post(route('donors.store'), ['name' => 'Marina', 'phone' => '', 'email' => ''])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('donors', ['name' => 'Mina Samir', 'phone' => null, 'email' => null, 'tenant_id' => $this->tenant->id]);
        $this->assertDatabaseHas('donors', ['name' => 'Marina', 'phone' => null, 'email' => null]);

        $this->actingAs($this->agent)->post(route('donors.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($this->agent)->post(route('donors.store'), ['name' => 'Bad', 'email' => 'not-an-email'])->assertSessionHasErrors('email');
    }

    public function test_an_admin_can_edit_a_donor_and_the_list_is_searchable(): void
    {
        $donor = Donor::create(['tenant_id' => $this->tenant->id, 'name' => 'Mina']);
        Donor::create(['tenant_id' => $this->tenant->id, 'name' => 'Peter', 'phone' => '0100']);

        $this->actingAs($this->admin)->put(route('donors.update', $donor), ['name' => 'Mina Samir', 'phone' => '0111'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('donors', ['id' => $donor->id, 'name' => 'Mina Samir', 'phone' => '0111']);

        $this->actingAs($this->admin)->get(route('donors.index', ['search' => 'Peter']))
            ->assertInertia(fn ($page) => $page->component('Donations/Donors')->has('donors.data', 1));
    }

    public function test_recording_a_donation_hands_back_the_link_to_its_receipt(): void
    {
        $response = $this->actingAs($this->agent)->post(route('donations.store'), $this->payload());
        $donation = Donation::firstOrFail();

        $response->assertSessionHas('receipt_url', route('donations.receipt', $donation));

        $this->actingAs($this->agent)->get(route('donations.index'))
            ->assertInertia(fn ($page) => $page->where('flash.receipt_url', route('donations.receipt', $donation)));
    }

    public function test_a_refused_donation_hands_back_no_receipt_link(): void
    {
        GlMapping::where('role', GlMappingRole::DonationCashPayment)->delete();

        $this->actingAs($this->agent)->post(route('donations.store'), $this->payload())
            ->assertSessionHas('error')
            ->assertSessionMissing('receipt_url');
    }

    public function test_the_receipt_is_a_pdf_for_a_received_donation_and_refused_once_cancelled(): void
    {
        $donor = Donor::create(['tenant_id' => $this->tenant->id, 'name' => 'مينا سمير']);
        $donation = $this->record(['donor_id' => $donor->id, 'amount' => 1250.5]);

        $response = $this->actingAs($this->agent)->get(route('donations.receipt', $donation));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('donation-receipt-000001.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->streamedContent());

        $this->actingAs($this->admin)->post(route('donations.cancel', $donation));
        $this->actingAs($this->agent)->get(route('donations.receipt', $donation))->assertSessionHas('error');
    }

    public function test_the_receipt_can_be_opened_inline_for_printing_or_downloaded(): void
    {
        $donation = $this->record();

        $download = $this->actingAs($this->agent)->get(route('donations.receipt', $donation));
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));

        $inline = $this->actingAs($this->agent)->get(route('donations.receipt', ['donation' => $donation, 'inline' => 1]));
        $inline->assertOk();
        $this->assertStringStartsWith('inline', (string) $inline->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $inline->headers->get('Content-Type'));
    }

    public function test_the_receipt_shows_the_donor_amount_in_words_currency_and_center(): void
    {
        $donor = Donor::create(['tenant_id' => $this->tenant->id, 'name' => 'مينا سمير']);
        $donation = $this->record(['donor_id' => $donor->id, 'amount' => 1250.5]);

        $html = view('pdf.donation-receipt', app(DonationService::class)->receiptData($donation))->render();

        $this->assertStringContainsString('مينا سمير', $html);
        $this->assertStringContainsString('1,250.50 EGP', $html);
        $this->assertStringContainsString('فقط ألف ومائتان وخمسون جنيهاً مصرياً وخمسون قرشاً لا غير', $html);
        $this->assertStringContainsString('جنيه مصري', $html);
        $this->assertStringContainsString('Charity', $html);
        $this->assertStringContainsString('St. Mary Church', $html);
        $this->assertStringContainsString(e($this->agent->name), $html);
    }

    public function test_a_donations_only_user_is_sent_from_the_dashboard_to_donations(): void
    {
        $this->actingAs($this->agent)->get(route('dashboard'))->assertRedirect(route('donations.index'));
    }
}
