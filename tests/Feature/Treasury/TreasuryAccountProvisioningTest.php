<?php

namespace Tests\Feature\Treasury;

use App\Enums\AccountType;
use App\Enums\GlMappingRole;
use App\Models\Account;
use App\Models\GlMapping;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Treasury\Services\TreasuryAccountProvisioner;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TreasuryAccountProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, bool $treasury = false): Tenant
    {
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'base_currency' => 'EGP', 'is_treasury' => $treasury]);
        (new ChartOfAccountsSeeder)->run($tenant->id);

        return $tenant;
    }

    private function mappedAccount(Tenant $tenant, GlMappingRole $role): ?Account
    {
        $accountId = GlMapping::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', $role->value)->value('account_id');

        return $accountId ? Account::query()->withoutGlobalScopes()->find($accountId) : null;
    }

    private function accountCount(Tenant $tenant): int
    {
        return Account::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
    }

    public function test_a_tenant_gets_its_treasury_accounts_under_the_usual_parents_and_mapped(): void
    {
        $tenant = $this->tenant('church');

        app(TreasuryAccountProvisioner::class)->provision($tenant);

        $cash = $this->mappedAccount($tenant, GlMappingRole::TreasuryCash);
        $receivable = $this->mappedAccount($tenant, GlMappingRole::TreasuryReceivable);
        $payable = $this->mappedAccount($tenant, GlMappingRole::TreasuryPayable);

        $this->assertSame('Due from Treasury', $receivable->name);
        $this->assertSame(AccountType::Asset, $receivable->type);
        $this->assertSame('Due to Treasury', $payable->name);
        $this->assertSame(AccountType::Liability, $payable->type);
        $this->assertSame(AccountType::Asset, $cash->type);
        $this->assertSame('1200', Account::query()->withoutGlobalScopes()->find($receivable->parent_id)->code);
        $this->assertSame('2200', Account::query()->withoutGlobalScopes()->find($payable->parent_id)->code);
        $this->assertTrue(Account::codeNestsUnder($receivable->code, '1200'));
    }

    public function test_the_treasury_tenant_gets_held_advances_and_a_safe(): void
    {
        $treasury = $this->tenant('avarewase', treasury: true);

        app(TreasuryAccountProvisioner::class)->provision($treasury);

        $this->assertSame('Held for Tenants', $this->mappedAccount($treasury, GlMappingRole::TreasuryHeld)->name);
        $this->assertSame('Advances to Tenants', $this->mappedAccount($treasury, GlMappingRole::TreasuryAdvances)->name);
        $this->assertTrue(Account::query()->withoutGlobalScopes()->where('tenant_id', $treasury->id)->where('name', 'Treasury Safe')->exists());
        $this->assertNull($this->mappedAccount($treasury, GlMappingRole::TreasuryReceivable));
    }

    public function test_running_it_again_creates_nothing_new(): void
    {
        $tenant = $this->tenant('church');
        $provisioner = app(TreasuryAccountProvisioner::class);

        $provisioner->provision($tenant);
        $accounts = $this->accountCount($tenant);
        $mappings = GlMapping::query()->withoutGlobalScopes()->count();

        $second = $provisioner->provision($tenant);

        $this->assertSame($accounts, $this->accountCount($tenant));
        $this->assertSame($mappings, GlMapping::query()->withoutGlobalScopes()->count());
        $this->assertSame(['mapped'], array_values(array_unique(array_column($second, 'status'))));
    }

    public function test_an_account_that_already_exists_is_linked_not_duplicated(): void
    {
        $tenant = $this->tenant('church');
        $existing = Account::create(['tenant_id' => $tenant->id, 'code' => '1250', 'name' => 'due from treasury', 'type' => AccountType::Asset, 'normal_balance' => 'debit']);

        $results = app(TreasuryAccountProvisioner::class)->provision($tenant);

        $this->assertSame($existing->id, $this->mappedAccount($tenant, GlMappingRole::TreasuryReceivable)->id);
        $this->assertSame(1, Account::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereRaw('LOWER(name) = ?', ['due from treasury'])->count());
        $this->assertSame('linked', collect($results)->firstWhere('role', 'treasury_receivable')['status']);
    }

    public function test_an_existing_mapping_is_left_alone(): void
    {
        $tenant = $this->tenant('church');
        $mine = Account::create(['tenant_id' => $tenant->id, 'code' => '1260', 'name' => 'My own receivable', 'type' => AccountType::Asset, 'normal_balance' => 'debit']);
        GlMapping::create(['tenant_id' => $tenant->id, 'role' => GlMappingRole::TreasuryReceivable->value, 'account_id' => $mine->id]);

        app(TreasuryAccountProvisioner::class)->provision($tenant);

        $this->assertSame($mine->id, $this->mappedAccount($tenant, GlMappingRole::TreasuryReceivable)->id);
        $this->assertFalse(Account::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Due from Treasury')->exists());
    }

    public function test_the_cash_account_already_used_for_donations_is_reused(): void
    {
        $tenant = $this->tenant('church');
        $cash = Account::create(['tenant_id' => $tenant->id, 'code' => '1210', 'name' => 'Church Cash', 'type' => AccountType::Asset, 'normal_balance' => 'debit']);
        GlMapping::create(['tenant_id' => $tenant->id, 'role' => GlMappingRole::DonationCashPayment->value, 'account_id' => $cash->id]);

        app(TreasuryAccountProvisioner::class)->provision($tenant);

        $this->assertSame($cash->id, $this->mappedAccount($tenant, GlMappingRole::TreasuryCash)->id);
        $this->assertFalse(Account::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Cash on Hand')->exists());
    }

    public function test_an_account_with_postings_is_never_turned_into_a_parent(): void
    {
        $tenant = $this->tenant('church');
        $current = Account::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('code', '1200')->first();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $entry = DB::table('journal_entries')->insertGetId(['tenant_id' => $tenant->id, 'date' => '2026-11-01', 'description' => 'Opening', 'source_type' => 'manual', 'created_by' => $user->id, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('journal_lines')->insert(['journal_entry_id' => $entry, 'account_id' => $current->id, 'debit' => 10, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()]);

        app(TreasuryAccountProvisioner::class)->provision($tenant);

        $receivable = $this->mappedAccount($tenant, GlMappingRole::TreasuryReceivable);
        $this->assertNull($receivable->parent_id);
        $this->assertFalse($current->children()->exists());
        $this->assertTrue(str_starts_with($receivable->code, '1'));
    }

    public function test_the_command_provisions_every_tenant_and_skips_what_exists(): void
    {
        $church = $this->tenant('church');
        $treasury = $this->tenant('avarewase', treasury: true);

        $this->artisan('treasury:provision-accounts')->assertSuccessful();
        $accounts = [$this->accountCount($church), $this->accountCount($treasury)];

        $this->artisan('treasury:provision-accounts')->assertSuccessful();

        $this->assertNotNull($this->mappedAccount($church, GlMappingRole::TreasuryPayable));
        $this->assertNotNull($this->mappedAccount($treasury, GlMappingRole::TreasuryHeld));
        $this->assertSame($accounts, [$this->accountCount($church), $this->accountCount($treasury)]);
    }

    public function test_switching_the_module_on_provisions_the_tenant(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $tenant = $this->tenant('church');
        $admin = User::factory()->create();
        $admin->assignRole('Platform Admin');

        $this->actingAs($admin)->put(route('platform.tenants.modules', $tenant), ['modules' => ['treasury']])->assertSessionHasNoErrors();

        $this->assertNotNull($this->mappedAccount($tenant, GlMappingRole::TreasuryReceivable));
    }
}
