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
}
