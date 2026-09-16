<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');
    }

    public function test_account_code_must_start_with_the_digit_for_its_type(): void
    {
        $response = $this->actingAs($this->user)->post(route('accounts.store'), [
            'code' => '2000',
            'name' => 'Cash',
            'type' => AccountType::Asset->value,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('accounts', ['code' => '2000']);
    }

    public function test_parent_account_must_share_the_same_type(): void
    {
        $liabilityParent = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '2000',
            'name' => 'Liabilities',
            'type' => AccountType::Liability,
            'normal_balance' => 'credit',
        ]);

        $response = $this->actingAs($this->user)->post(route('accounts.store'), [
            'code' => '1000',
            'name' => 'Cash',
            'type' => AccountType::Asset->value,
            'parent_id' => $liabilityParent->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('accounts', ['code' => '1000']);
    }

    public function test_account_name_must_be_unique_within_its_type(): void
    {
        Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1000',
            'name' => 'Cash',
            'type' => AccountType::Asset,
            'normal_balance' => 'debit',
        ]);

        $response = $this->actingAs($this->user)->post(route('accounts.store'), [
            'code' => '1200',
            'name' => 'Cash',
            'type' => AccountType::Asset->value,
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertSame(1, Account::where('name', 'Cash')->count());
    }

    public function test_account_name_may_repeat_across_different_types(): void
    {
        Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1000',
            'name' => 'Reserves',
            'type' => AccountType::Asset,
            'normal_balance' => 'debit',
        ]);

        $response = $this->actingAs($this->user)->post(route('accounts.store'), [
            'code' => '3000',
            'name' => 'Reserves',
            'type' => AccountType::Equity->value,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(2, Account::where('name', 'Reserves')->count());
    }

    public function test_child_account_code_must_nest_under_its_parents_code(): void
    {
        $parent = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1100',
            'name' => 'Current Assets',
            'type' => AccountType::Asset,
            'normal_balance' => 'debit',
        ]);

        $response = $this->actingAs($this->user)->post(route('accounts.store'), [
            'code' => '1200',
            'name' => 'Cash',
            'type' => AccountType::Asset->value,
            'parent_id' => $parent->id,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('accounts', ['code' => '1200']);
    }

    public function test_child_account_code_skipping_a_numbering_level_is_rejected(): void
    {
        $parent = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1100',
            'name' => 'Current Assets',
            'type' => AccountType::Asset,
            'normal_balance' => 'debit',
        ]);

        // 1101 varies the units digit while 1100 only has the tens digit
        // free — that's a leaf two levels down, not a direct child.
        $response = $this->actingAs($this->user)->post(route('accounts.store'), [
            'code' => '1101',
            'name' => 'Petty Cash',
            'type' => AccountType::Asset->value,
            'parent_id' => $parent->id,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('accounts', ['code' => '1101']);
    }

    public function test_child_account_code_nesting_under_its_parent_is_accepted(): void
    {
        $parent = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1100',
            'name' => 'Current Assets',
            'type' => AccountType::Asset,
            'normal_balance' => 'debit',
        ]);

        $response = $this->actingAs($this->user)->post(route('accounts.store'), [
            'code' => '1110',
            'name' => 'Cash',
            'type' => AccountType::Asset->value,
            'parent_id' => $parent->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('accounts', ['code' => '1110', 'parent_id' => $parent->id]);
    }

    public function test_creating_an_account_with_an_opening_balance_posts_a_balanced_journal_entry(): void
    {
        $response = $this->actingAs($this->user)->post(route('accounts.store'), [
            'code' => '1000',
            'name' => 'Cash',
            'type' => AccountType::Asset->value,
            'opening_balance' => 500,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $cash = Account::where('code', '1000')->firstOrFail();
        $equity = Account::where('code', '3900')->firstOrFail();

        $this->assertSame(AccountType::Equity, $equity->type);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $equity->id, 'debit' => 0, 'credit' => 500]);
    }

    public function test_creating_an_account_without_an_opening_balance_posts_no_journal_entry(): void
    {
        $this->actingAs($this->user)->post(route('accounts.store'), [
            'code' => '1000',
            'name' => 'Cash',
            'type' => AccountType::Asset->value,
        ])->assertSessionHasNoErrors();

        $cash = Account::where('code', '1000')->firstOrFail();

        $this->assertSame(0, $cash->journalLines()->count());
        $this->assertDatabaseMissing('accounts', ['code' => '3900']);
    }

    public function test_accounts_index_returns_the_full_unpaginated_list_for_the_tree_view(): void
    {
        $parent = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1000',
            'name' => 'Assets',
            'type' => AccountType::Asset,
            'normal_balance' => 'debit',
        ]);

        $child = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1010',
            'name' => 'Cash',
            'type' => AccountType::Asset,
            'normal_balance' => 'debit',
            'parent_id' => $parent->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('accounts.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Accounts/Index')
            ->has('accounts', 2)
            ->where('accounts.0.id', $parent->id)
            ->where('accounts.1.parent_id', $parent->id)
            ->where('accounts.1.id', $child->id)
        );
    }
}
