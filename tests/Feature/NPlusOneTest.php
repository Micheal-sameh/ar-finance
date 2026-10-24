<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\CostCenter;
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

    public function test_profit_and_loss_grouped_by_month_uses_one_query_regardless_of_month_count(): void
    {
        $this->warmAuthCaches('reports.profit-and-loss');
        $this->actingAs($this->user);

        $url = fn (string $from) => route('reports.profit-and-loss', ['from' => $from, 'to' => '2026-12-31', 'group_by' => 'month']);

        $twoMonths = $this->countQueriesFor(fn () => $this->get($url('2026-11-01')));
        $twelveMonths = $this->countQueriesFor(fn () => $this->get($url('2026-01-01')));

        $this->assertSame($twoMonths, $twelveMonths);
    }

    public function test_dashboard_query_count_does_not_depend_on_trend_months(): void
    {
        $this->warmAuthCaches('dashboard');

        $journalLineQueries = 0;
        DB::listen(function ($query) use (&$journalLineQueries) {
            if (str_contains($query->sql, 'from "journal_lines"')) {
                $journalLineQueries++;
            }
        });

        $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

        // current + comparison P&L, AR, AP and one query for the 12-month trend.
        $this->assertLessThanOrEqual(6, $journalLineQueries);
    }

    public function test_users_index_query_count_does_not_grow_with_row_count(): void
    {
        $platform = User::factory()->create(['tenant_id' => null]);
        $platform->assignRole('Platform Admin');
        $other = Tenant::create(['name' => 'Other Co', 'slug' => 'other-co']);
        $this->warmAuthCaches('users.index');
        $this->actingAs($platform)->get(route('users.index'));

        $this->makeUsers(3, $other);
        $few = $this->countQueriesFor(fn () => $this->actingAs($platform)->get(route('users.index')));

        $this->makeUsers(20, $other);
        $many = $this->countQueriesFor(fn () => $this->actingAs($platform)->get(route('users.index')));

        $this->assertSame($few, $many);
    }

    private function makeUsers(int $count, Tenant $extraTenant): void
    {
        User::factory()->count($count)->create(['tenant_id' => $this->user->tenant_id])
            ->each(function (User $user) use ($extraTenant) {
                $user->tenants()->attach($extraTenant->id);
                $user->assignRole('Accountant');
            });
    }

    private function postEntries(int $count, int $cashId, int $revenueId): void
    {
        $center = CostCenter::firstOrCreate(
            ['tenant_id' => $this->user->tenant_id, 'name' => 'General'],
            ['type' => 'cost'],
        );

        for ($i = 0; $i < $count; $i++) {
            $this->actingAs($this->user)->post(route('journals.store'), [
                'date' => now()->toDateString(),
                'description' => "Entry {$i}",
                'lines' => [
                    ['account_id' => $cashId, 'debit' => 10, 'credit' => 0, 'cost_center_id' => $center->id],
                    ['account_id' => $revenueId, 'debit' => 0, 'credit' => 10, 'cost_center_id' => $center->id],
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
