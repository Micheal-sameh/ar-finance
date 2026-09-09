<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the list endpoints against N+1 regressions: query count must stay
 * flat as row count grows, proving repositories eager-load rather than
 * lazy-loading per row in a loop.
 */
class NPlusOneTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->user->assignRole('Super Admin');
    }

    /**
     * The first authorized request of the test pays a handful of one-time
     * queries warming Spatie's permission cache — unrelated to row count,
     * but noise for an N+1 comparison. Prime it with a real request (not
     * just a bare can() check, which warms a different sub-cache than the
     * full auth pipeline the endpoint actually runs) before measuring.
     */
    private function warmAuthCaches(string $routeName): void
    {
        $this->actingAs($this->user)->get(route($routeName));
    }

    public function test_accounts_index_query_count_does_not_grow_with_row_count(): void
    {
        $this->warmAuthCaches('accounts.index');

        Account::factory()->count(3)->create(['tenant_id' => $this->user->tenant_id, 'parent_id' => null]);
        $queriesWithFew = $this->countQueriesFor(fn () => $this->actingAs($this->user)->get(route('accounts.index')));

        Account::factory()->count(20)->create(['tenant_id' => $this->user->tenant_id, 'parent_id' => null]);
        $queriesWithMany = $this->countQueriesFor(fn () => $this->actingAs($this->user)->get(route('accounts.index')));

        $this->assertSame($queriesWithFew, $queriesWithMany);
    }

    public function test_journals_index_query_count_does_not_grow_with_row_count(): void
    {
        $this->warmAuthCaches('journals.index');

        $cash = Account::factory()->create(['tenant_id' => $this->user->tenant_id, 'type' => AccountType::Asset, 'normal_balance' => 'debit']);
        $revenue = Account::factory()->create(['tenant_id' => $this->user->tenant_id, 'type' => AccountType::Revenue, 'normal_balance' => 'credit']);

        $this->postEntries(2, $cash->id, $revenue->id);
        $queriesWithFew = $this->countQueriesFor(fn () => $this->actingAs($this->user)->get(route('journals.index')));

        $this->postEntries(15, $cash->id, $revenue->id);
        $queriesWithMany = $this->countQueriesFor(fn () => $this->actingAs($this->user)->get(route('journals.index')));

        $this->assertSame($queriesWithFew, $queriesWithMany);
    }

    private function postEntries(int $count, int $cashId, int $revenueId): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->actingAs($this->user)->post(route('journals.store'), [
                'date' => now()->toDateString(),
                'description' => "Entry {$i}",
                'lines' => [
                    ['account_id' => $cashId, 'debit' => 10, 'credit' => 0],
                    ['account_id' => $revenueId, 'debit' => 0, 'credit' => 10],
                ],
            ]);
        }
    }

    private function countQueriesFor(\Closure $callback): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $callback();

        $count = count(DB::getQueryLog());

        DB::flushQueryLog();
        DB::disableQueryLog();

        return $count;
    }
}
