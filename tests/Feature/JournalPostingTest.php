<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalPostingTest extends TestCase
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

    public function test_balanced_journal_entry_posts_through_the_http_form(): void
    {
        $cash = $this->account('1000', 'Cash', AccountType::Asset, 'debit');
        $revenue = $this->account('4000', 'Sales Revenue', AccountType::Revenue, 'credit');

        $response = $this->actingAs($this->user)->post(route('journals.store'), [
            'date' => now()->toDateString(),
            'description' => 'Cash sale',
            'reference' => 'INV-001',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 150, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 150],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('journal_entries', ['description' => 'Cash sale']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $cash->id, 'debit' => 150]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $revenue->id, 'credit' => 150]);
    }

    public function test_unbalanced_journal_entry_is_rejected_with_no_rows_written(): void
    {
        $cash = $this->account('1000', 'Cash', AccountType::Asset, 'debit');
        $revenue = $this->account('4000', 'Sales Revenue', AccountType::Revenue, 'credit');

        $response = $this->actingAs($this->user)->post(route('journals.store'), [
            'date' => now()->toDateString(),
            'description' => 'Broken entry',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 90],
            ],
        ]);

        $response->assertSessionHasErrors('lines');
        $this->assertDatabaseMissing('journal_entries', ['description' => 'Broken entry']);
    }

    public function test_trial_balance_reflects_posted_entries_and_stays_balanced(): void
    {
        $cash = $this->account('1000', 'Cash', AccountType::Asset, 'debit');
        $revenue = $this->account('4000', 'Sales Revenue', AccountType::Revenue, 'credit');

        $this->actingAs($this->user)->post(route('journals.store'), [
            'date' => now()->toDateString(),
            'description' => 'Cash sale',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 200, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 200],
            ],
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($this->user)->get(route('reports.trial-balance'));

        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/Reports/TrialBalance')
            ->where('report.is_balanced', true)
            ->where('report.total_debit', 200)
            ->where('report.total_credit', 200)
        );
    }

    public function test_account_with_journal_lines_cannot_be_deleted(): void
    {
        $cash = $this->account('1000', 'Cash', AccountType::Asset, 'debit');
        $revenue = $this->account('4000', 'Sales Revenue', AccountType::Revenue, 'credit');

        $this->actingAs($this->user)->post(route('journals.store'), [
            'date' => now()->toDateString(),
            'description' => 'Cash sale',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 50, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 50],
            ],
        ]);

        $this->actingAs($this->user)->delete(route('accounts.destroy', $cash));

        $this->assertDatabaseHas('accounts', ['id' => $cash->id]);
    }
}
