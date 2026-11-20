<?php

namespace Tests\Feature\Treasury;

use App\Enums\AccountType;
use App\Enums\GlMappingRole;
use App\Models\Account;
use App\Models\GlMapping;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Treasury\Enums\TreasuryTransferStatus;
use App\Modules\Treasury\Models\TreasuryTransfer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TreasuryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $treasury;

    private Tenant $church;

    private Tenant $otherChurch;

    private User $treasurer;

    private User $churchAdmin;

    private User $otherChurchAdmin;

    /** @var array<string, Account> */
    private array $accounts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->treasury = Tenant::create(['name' => 'Treasury', 'slug' => 'treasury', 'base_currency' => 'EGP', 'modules' => ['treasury'], 'is_treasury' => true]);
        $this->church = Tenant::create(['name' => 'St. Mary', 'slug' => 'st-mary', 'base_currency' => 'EGP', 'modules' => ['treasury']]);
        $this->otherChurch = Tenant::create(['name' => 'St. George', 'slug' => 'st-george', 'base_currency' => 'EGP', 'modules' => ['treasury']]);

        $this->treasurer = $this->user('Treasurer', $this->treasury);
        $this->churchAdmin = $this->user('Admin', $this->church);
        $this->otherChurchAdmin = $this->user('Admin', $this->otherChurch);

        $this->treasuryAccounts(['safe' => ['1010', 'Safe', AccountType::Asset], 'bank' => ['1020', 'Bank', AccountType::Asset]]);
        $this->mapped($this->treasury, GlMappingRole::TreasuryHeld, $this->account($this->treasury, '2100', 'Held for tenants', AccountType::Liability), 'held');
        $this->mapped($this->treasury, GlMappingRole::TreasuryAdvances, $this->account($this->treasury, '1500', 'Advances to tenants', AccountType::Asset), 'advances');

        foreach (['church' => $this->church, 'other' => $this->otherChurch] as $key => $tenant) {
            $this->mapped($tenant, GlMappingRole::TreasuryCash, $this->account($tenant, '1010', 'Cash', AccountType::Asset), "{$key}_cash");
            $this->mapped($tenant, GlMappingRole::TreasuryReceivable, $this->account($tenant, '1600', 'Due from treasury', AccountType::Asset), "{$key}_receivable");
            $this->mapped($tenant, GlMappingRole::TreasuryPayable, $this->account($tenant, '2600', 'Due to treasury', AccountType::Liability), "{$key}_payable");
        }
    }

    private function user(string $role, Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole($role);

        return $user;
    }

    private function account(Tenant $tenant, string $code, string $name, AccountType $type): Account
    {
        return Account::create([
            'tenant_id' => $tenant->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $type === AccountType::Asset ? 'debit' : 'credit',
        ]);
    }

    /**
     * @param  array<string, array{0: string, 1: string, 2: AccountType}>  $definitions
     */
    private function treasuryAccounts(array $definitions): void
    {
        foreach ($definitions as $key => [$code, $name, $type]) {
            $this->accounts[$key] = $this->account($this->treasury, $code, $name, $type);
        }
    }

    private function mapped(Tenant $tenant, GlMappingRole $role, Account $account, string $key): void
    {
        GlMapping::create(['tenant_id' => $tenant->id, 'role' => $role->value, 'account_id' => $account->id]);
        $this->accounts[$key] = $account;
    }

    /** Debit minus credit of an account. */
    private function balance(string $key): float
    {
        $totals = DB::table('journal_lines')->where('account_id', $this->accounts[$key]->id)
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')->first();

        return round((float) $totals->debit - (float) $totals->credit, 2);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function requestAs(User $user, string $type, float $amount, array $overrides = [], string $accountKey = 'church_cash')
    {
        return $this->actingAs($user)->post(route('treasury.request'), array_merge([
            'type' => $type,
            'amount' => $amount,
            'transfer_date' => '2026-11-01',
            'tenant_account_id' => $this->accounts[$accountKey]->id,
        ], $overrides));
    }

    private function accept(TreasuryTransfer $transfer, float $amount, string $location = 'safe')
    {
        return $this->actingAs($this->treasurer)->post(route('treasury.accept', $transfer), [
            'accepted_amount' => $amount,
            'treasury_account_id' => $this->accounts[$location]->id,
        ]);
    }

    private function deposit(float $amount = 1000, ?float $accepted = null, string $location = 'safe'): TreasuryTransfer
    {
        $this->requestAs($this->churchAdmin, 'deposit', $amount)->assertSessionHasNoErrors();
        $transfer = TreasuryTransfer::query()->latest('id')->first();
        $this->accept($transfer, $accepted ?? $amount, $location)->assertSessionHas('success');

        return $transfer->fresh();
    }

    public function test_a_request_posts_nothing_until_the_treasurer_accepts(): void
    {
        $this->requestAs($this->churchAdmin, 'deposit', 1000)->assertSessionHas('success');

        $transfer = TreasuryTransfer::query()->firstOrFail();
        $this->assertSame(TreasuryTransferStatus::Requested, $transfer->status);
        $this->assertSame($this->church->id, $transfer->tenant_id);
        $this->assertSame($this->treasury->id, $transfer->treasury_tenant_id);
        $this->assertSame(0, DB::table('journal_entries')->count());
    }

    public function test_accepting_a_deposit_posts_one_balanced_entry_in_each_ledger(): void
    {
        $this->requestAs($this->churchAdmin, 'deposit', 1000);
        $transfer = TreasuryTransfer::query()->firstOrFail();

        $this->accept($transfer, 900)->assertSessionHas('success');

        $transfer->refresh();
        $this->assertSame(TreasuryTransferStatus::Accepted, $transfer->status);
        $this->assertSame('900.00', $transfer->accepted_amount);

        // Tenant: Dr due from treasury / Cr cash. Treasury: Dr safe / Cr held for tenants.
        $this->assertSame(900.0, $this->balance('church_receivable'));
        $this->assertSame(-900.0, $this->balance('church_cash'));
        $this->assertSame(900.0, $this->balance('safe'));
        $this->assertSame(-900.0, $this->balance('held'));

        $entries = DB::table('journal_entries')->where('source_type', 'treasury')->get();
        $this->assertCount(2, $entries);
        $this->assertEqualsCanonicalizing([$this->church->id, $this->treasury->id], $entries->pluck('tenant_id')->all());

        foreach ($entries as $entry) {
            $lines = DB::table('journal_lines')->where('journal_entry_id', $entry->id)->get();
            $this->assertCount(2, $lines);
            $this->assertEquals($lines->sum('debit'), $lines->sum('credit'));
        }
    }

    public function test_the_accepted_amount_cannot_exceed_the_requested_one(): void
    {
        $this->requestAs($this->churchAdmin, 'deposit', 500);
        $transfer = TreasuryTransfer::query()->firstOrFail();

        $this->accept($transfer, 600)->assertSessionHas('error');

        $this->assertSame(TreasuryTransferStatus::Requested, $transfer->fresh()->status);
        $this->assertSame(0, DB::table('journal_entries')->count());
    }

    public function test_a_request_can_only_be_decided_once(): void
    {
        $transfer = $this->deposit(500);

        $this->accept($transfer, 500)->assertSessionHas('error');
        $this->actingAs($this->treasurer)->post(route('treasury.reject', $transfer))->assertSessionHas('error');
        $this->assertSame(500.0, $this->balance('safe'));
    }

    public function test_rejecting_a_request_posts_nothing(): void
    {
        $this->requestAs($this->churchAdmin, 'deposit', 500);
        $transfer = TreasuryTransfer::query()->firstOrFail();

        $this->actingAs($this->treasurer)->post(route('treasury.reject', $transfer), ['reason' => 'Not counted'])->assertSessionHas('success');

        $this->assertSame(TreasuryTransferStatus::Rejected, $transfer->fresh()->status);
        $this->assertSame('Not counted', $transfer->fresh()->reject_reason);
        $this->assertSame(0, DB::table('journal_entries')->count());
    }

    public function test_a_withdrawal_is_limited_to_what_the_treasury_holds_for_the_tenant(): void
    {
        $this->deposit(1000);

        $this->requestAs($this->churchAdmin, 'withdrawal', 1500)->assertSessionHas('error');
        $this->assertSame(1, TreasuryTransfer::query()->count());

        $this->requestAs($this->churchAdmin, 'withdrawal', 400)->assertSessionHas('success');
        $withdrawal = TreasuryTransfer::query()->latest('id')->first();
        $this->accept($withdrawal, 400)->assertSessionHas('success');

        // Tenant: Dr cash / Cr due from treasury. Treasury: Dr held / Cr safe.
        $this->assertSame(600.0, $this->balance('church_receivable'));
        $this->assertSame(-600.0, $this->balance('church_cash'));
        $this->assertSame(600.0, $this->balance('safe'));
        $this->assertSame(-600.0, $this->balance('held'));
    }

    public function test_funding_another_tenant_is_an_advance_that_leaves_the_depositors_claim_untouched(): void
    {
        $this->deposit(1000);

        $this->actingAs($this->treasurer)->post(route('treasury.fund'), [
            'tenant_id' => $this->otherChurch->id,
            'amount' => 400,
            'transfer_date' => '2026-11-02',
            'treasury_account_id' => $this->accounts['safe']->id,
        ])->assertSessionHas('success');

        // Treasury: Dr advances / Cr safe. Other tenant: Dr cash / Cr due to treasury.
        $this->assertSame(400.0, $this->balance('advances'));
        $this->assertSame(600.0, $this->balance('safe'));
        $this->assertSame(400.0, $this->balance('other_cash'));
        $this->assertSame(-400.0, $this->balance('other_payable'));
        // What the treasury owes the depositing tenant did not move.
        $this->assertSame(-1000.0, $this->balance('held'));
        $this->assertSame(1000.0, $this->balance('church_receivable'));
    }

    public function test_funding_is_refused_when_the_safe_does_not_hold_enough(): void
    {
        $this->deposit(100);

        $this->actingAs($this->treasurer)->post(route('treasury.fund'), [
            'tenant_id' => $this->otherChurch->id,
            'amount' => 250,
            'transfer_date' => '2026-11-02',
            'treasury_account_id' => $this->accounts['safe']->id,
        ])->assertSessionHas('error');

        $this->assertSame(1, TreasuryTransfer::query()->count());
        $this->assertSame(0.0, $this->balance('other_cash'));
    }

    public function test_funding_needs_the_tenants_treasury_cash_account_to_be_mapped(): void
    {
        $this->deposit(500);
        GlMapping::query()->withoutGlobalScopes()->where('tenant_id', $this->otherChurch->id)->where('role', GlMappingRole::TreasuryCash->value)->delete();

        $this->actingAs($this->treasurer)->post(route('treasury.fund'), [
            'tenant_id' => $this->otherChurch->id,
            'amount' => 100,
            'transfer_date' => '2026-11-02',
            'treasury_account_id' => $this->accounts['safe']->id,
        ])->assertSessionHas('error');

        $this->assertSame(500.0, $this->balance('safe'));
    }

    public function test_a_repayment_is_limited_to_what_the_tenant_owes_and_clears_the_advance(): void
    {
        $this->deposit(1000);
        $this->actingAs($this->treasurer)->post(route('treasury.fund'), [
            'tenant_id' => $this->otherChurch->id,
            'amount' => 400,
            'transfer_date' => '2026-11-02',
            'treasury_account_id' => $this->accounts['safe']->id,
        ]);

        $this->requestAs($this->otherChurchAdmin, 'repayment', 500, [], 'other_cash')->assertSessionHas('error');

        $this->requestAs($this->otherChurchAdmin, 'repayment', 400, [], 'other_cash')->assertSessionHas('success');
        $this->accept(TreasuryTransfer::query()->latest('id')->first(), 400)->assertSessionHas('success');

        $this->assertSame(0.0, $this->balance('advances'));
        $this->assertSame(0.0, $this->balance('other_payable'));
        $this->assertSame(0.0, $this->balance('other_cash'));
        $this->assertSame(1000.0, $this->balance('safe'));
    }

    public function test_the_treasurer_moves_money_between_cash_and_bank_in_one_entry(): void
    {
        $this->deposit(1000);

        $this->actingAs($this->treasurer)->post(route('treasury.move'), [
            'amount' => 300,
            'transfer_date' => '2026-11-02',
            'treasury_account_id' => $this->accounts['safe']->id,
            'counter_account_id' => $this->accounts['bank']->id,
        ])->assertSessionHas('success');

        $this->assertSame(700.0, $this->balance('safe'));
        $this->assertSame(300.0, $this->balance('bank'));

        $move = TreasuryTransfer::query()->where('type', 'move')->firstOrFail();
        $this->assertNull($move->tenant_id);
        $entry = DB::table('journal_entries')->where('source_type', 'treasury')->where('source_id', $move->id)->get();
        $this->assertCount(1, $entry);

        // And back again, bank to cash.
        $this->actingAs($this->treasurer)->post(route('treasury.move'), [
            'amount' => 100,
            'transfer_date' => '2026-11-03',
            'treasury_account_id' => $this->accounts['bank']->id,
            'counter_account_id' => $this->accounts['safe']->id,
        ])->assertSessionHas('success');

        $this->assertSame(800.0, $this->balance('safe'));
        $this->assertSame(200.0, $this->balance('bank'));
    }

    public function test_a_move_needs_two_different_accounts_and_enough_money(): void
    {
        $this->deposit(100);

        $this->actingAs($this->treasurer)->post(route('treasury.move'), [
            'amount' => 50,
            'transfer_date' => '2026-11-02',
            'treasury_account_id' => $this->accounts['safe']->id,
            'counter_account_id' => $this->accounts['safe']->id,
        ])->assertSessionHasErrors('counter_account_id');

        $this->actingAs($this->treasurer)->post(route('treasury.move'), [
            'amount' => 500,
            'transfer_date' => '2026-11-02',
            'treasury_account_id' => $this->accounts['safe']->id,
            'counter_account_id' => $this->accounts['bank']->id,
        ])->assertSessionHas('error');

        $this->assertSame(100.0, $this->balance('safe'));
        $this->assertSame(0.0, $this->balance('bank'));
    }

    public function test_a_failure_on_one_side_leaves_no_entry_on_the_other(): void
    {
        GlMapping::query()->withoutGlobalScopes()->where('tenant_id', $this->treasury->id)->where('role', GlMappingRole::TreasuryHeld->value)->delete();
        $this->requestAs($this->churchAdmin, 'deposit', 500);
        $transfer = TreasuryTransfer::query()->firstOrFail();

        $this->accept($transfer, 500)->assertSessionHas('error');

        $this->assertSame(TreasuryTransferStatus::Requested, $transfer->fresh()->status);
        $this->assertSame(0, DB::table('journal_entries')->count());
    }

    public function test_voiding_a_deposit_reverses_both_ledgers(): void
    {
        $transfer = $this->deposit(1000);

        $this->actingAs($this->treasurer)->post(route('treasury.void', $transfer))->assertSessionHas('success');

        $this->assertSame(TreasuryTransferStatus::Voided, $transfer->fresh()->status);
        foreach (['church_receivable', 'church_cash', 'safe', 'held'] as $key) {
            $this->assertSame(0.0, $this->balance($key));
        }
    }

    public function test_voiding_a_deposit_is_refused_once_the_money_has_left_the_safe(): void
    {
        $transfer = $this->deposit(1000);
        $this->actingAs($this->treasurer)->post(route('treasury.move'), [
            'amount' => 1000,
            'transfer_date' => '2026-11-02',
            'treasury_account_id' => $this->accounts['safe']->id,
            'counter_account_id' => $this->accounts['bank']->id,
        ]);

        $this->actingAs($this->treasurer)->post(route('treasury.void', $transfer))->assertSessionHas('error');

        $this->assertSame(TreasuryTransferStatus::Accepted, $transfer->fresh()->status);
        $this->assertSame(1000.0, $this->balance('church_receivable'));
    }

    public function test_the_tenants_books_agree_with_the_treasurys_position(): void
    {
        $this->deposit(1000);
        $this->actingAs($this->treasurer)->post(route('treasury.fund'), [
            'tenant_id' => $this->otherChurch->id,
            'amount' => 400,
            'transfer_date' => '2026-11-02',
            'treasury_account_id' => $this->accounts['safe']->id,
        ]);

        $this->actingAs($this->treasurer)->get(route('treasury.index'))->assertInertia(fn ($page) => $page
            ->where('summary.mode', 'treasury')
            ->has('summary.positions', 2)
            ->where('summary.positions.0.matches', true)
            ->where('summary.positions.1.matches', true));

        $this->actingAs($this->churchAdmin)->get(route('treasury.index'))->assertInertia(fn ($page) => $page
            ->where('summary.mode', 'tenant')
            ->where('summary.held', 1000)
            ->where('summary.owed', 0));
    }

    public function test_only_people_with_the_right_permission_and_tenant_can_act(): void
    {
        $viewer = $this->user('Viewer', $this->church);

        $this->requestAs($viewer, 'deposit', 100)->assertForbidden();
        // The treasurer cannot ask the treasury for money: the treasury is not a depositor.
        $this->requestAs($this->treasurer, 'deposit', 100, [], 'safe')->assertForbidden();

        $this->requestAs($this->churchAdmin, 'deposit', 100);
        $transfer = TreasuryTransfer::query()->firstOrFail();

        // A tenant admin cannot accept their own request, even as an Admin role.
        $this->accept($transfer, 100)->assertSessionHasNoErrors();
        $this->actingAs($this->churchAdmin)->post(route('treasury.accept', $transfer), ['accepted_amount' => 100, 'treasury_account_id' => $this->accounts['safe']->id])->assertForbidden();
        $this->actingAs($this->churchAdmin)->post(route('treasury.fund'), ['tenant_id' => $this->church->id, 'amount' => 1, 'transfer_date' => '2026-11-01', 'treasury_account_id' => $this->accounts['safe']->id])->assertForbidden();
        $this->actingAs($this->churchAdmin)->post(route('treasury.move'), ['amount' => 1, 'transfer_date' => '2026-11-01', 'treasury_account_id' => $this->accounts['safe']->id, 'counter_account_id' => $this->accounts['bank']->id])->assertForbidden();
    }

    public function test_another_tenant_cannot_see_or_decide_a_transfer_it_is_not_part_of(): void
    {
        $this->requestAs($this->churchAdmin, 'deposit', 100);
        $transfer = TreasuryTransfer::query()->firstOrFail();

        $this->actingAs($this->otherChurchAdmin)->post(route('treasury.accept', $transfer), ['accepted_amount' => 100, 'treasury_account_id' => $this->accounts['safe']->id])->assertNotFound();
        $this->actingAs($this->otherChurchAdmin)->post(route('treasury.void', $transfer))->assertNotFound();

        $this->actingAs($this->otherChurchAdmin)->get(route('treasury.index'))->assertInertia(fn ($page) => $page->has('transfers.data', 0));
        $this->actingAs($this->churchAdmin)->get(route('treasury.index'))->assertInertia(fn ($page) => $page->has('transfers.data', 1));
    }

    public function test_an_account_from_another_tenant_is_rejected(): void
    {
        $this->requestAs($this->churchAdmin, 'deposit', 100, [], 'other_cash')->assertSessionHasErrors('tenant_account_id');
        $this->assertSame(0, TreasuryTransfer::query()->count());
    }

    public function test_the_module_must_be_switched_on(): void
    {
        $this->church->update(['modules' => []]);

        $this->actingAs($this->churchAdmin)->get(route('treasury.index'))->assertNotFound();
    }

    public function test_an_invalid_request_is_rejected(): void
    {
        $this->requestAs($this->churchAdmin, 'deposit', 0)->assertSessionHasErrors('amount');
        $this->requestAs($this->churchAdmin, 'deposit', 100, ['transfer_date' => '2999-01-01'])->assertSessionHasErrors('transfer_date');
        $this->requestAs($this->churchAdmin, 'funding', 100)->assertSessionHasErrors('type');
    }

    public function test_only_one_tenant_can_be_the_treasury(): void
    {
        $admin = $this->user('Platform Admin', $this->church);

        $this->actingAs($admin)->put(route('platform.tenants.update', $this->church), [
            'name' => 'St. Mary',
            'slug' => 'st-mary',
            'base_currency' => 'EGP',
            'is_treasury' => true,
        ])->assertSessionHasErrors('is_treasury');

        $this->assertFalse($this->church->fresh()->is_treasury);
    }
}
