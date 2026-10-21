<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_twelve_month_trend_expense_breakdown_and_overdue_summary(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('Super Admin');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->has('summary.trend', 12)
                ->where('summary.expense_breakdown', [])
                ->where('summary.overdue.count', 0)
                ->where('summary.overdue.total', 0)
                ->has('summary.overdue.rows', 0));
    }

    public function test_dashboard_uses_the_requested_period_and_compares_with_the_previous_one(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('Super Admin');

        $this->actingAs($user)
            ->get(route('dashboard', ['from' => '2026-01-01', 'to' => '2026-03-31']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
                ->has('summary.previous.revenue')
                ->where('summary.trend.11.month', 'Mar 2026'));

        $this->actingAs($user)
            ->get(route('dashboard', ['from' => '2026-03-31', 'to' => '2026-01-01']))
            ->assertSessionHasErrors('to');
    }
}
