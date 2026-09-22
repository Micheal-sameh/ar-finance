<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExcelExportTest extends TestCase
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

    /**
     * @return array<int, array<int, string>>
     */
    public static function exportRoutes(): array
    {
        return [
            ['accounts.export'],
            ['journals.export'],
            ['cost-centers.export'],
            ['clients.export'],
            ['vendors.export'],
            ['invoices.export'],
            ['expenses.export'],
            ['purchase-orders.export'],
            ['bills.export'],
            ['fixed-assets.export'],
            ['employees.export'],
            ['payroll-runs.export'],
            ['bank-accounts.export'],
            ['exchange-rates.export'],
            ['revaluation.export'],
            ['users.export'],
            ['reports.profit-and-loss.export'],
        ];
    }

    #[DataProvider('exportRoutes')]
    public function test_export_route_streams_an_xlsx_workbook(string $routeName): void
    {
        $response = $this->actingAs($this->user)->get(route($routeName));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_accounts_export_includes_matching_rows(): void
    {
        Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1000',
            'name' => 'Cash',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('accounts.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
