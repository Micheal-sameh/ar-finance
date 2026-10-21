<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Excel\ExcelExport;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class CrossTenantSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co', 'base_currency' => 'EGP']);
        $this->otherTenant = Tenant::create(['name' => 'Other Co', 'slug' => 'other-co', 'base_currency' => 'EGP']);
    }

    private function userWithRole(string $role, ?Tenant $tenant = null): User
    {
        $user = User::factory()->create(['tenant_id' => ($tenant ?? $this->tenant)->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_tenant_admin_cannot_update_a_user_in_another_tenant(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));
        $foreign = $this->userWithRole('Accountant', $this->otherTenant);

        $this->put(route('users.update', $foreign), ['status' => 'suspended', 'role' => 'Viewer'])
            ->assertForbidden();

        $this->assertSame('active', $foreign->fresh()->status->value);
        $this->assertTrue($foreign->fresh()->hasRole('Accountant'));
    }

    public function test_tenant_admin_cannot_update_a_platform_admin(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));
        $platformAdmin = $this->userWithRole('Platform Admin');

        $this->put(route('users.update', $platformAdmin), ['status' => 'suspended', 'role' => 'Viewer'])
            ->assertForbidden();

        $this->assertTrue($platformAdmin->fresh()->hasRole('Platform Admin'));
    }

    public function test_portal_manager_cannot_change_a_platform_admins_role(): void
    {
        $this->actingAs($this->userWithRole('Portal Manager'));
        $platformAdmin = $this->userWithRole('Platform Admin');

        $this->put(route('platform.users.assign-role', $platformAdmin), ['role' => 'Viewer'])
            ->assertForbidden();

        $this->assertTrue($platformAdmin->fresh()->hasRole('Platform Admin'));
    }

    public function test_foreign_tenant_records_are_rejected_by_validation(): void
    {
        $this->actingAs($this->userWithRole('Super Admin'));

        $foreignClient = Client::withoutGlobalScopes()->create(['tenant_id' => $this->otherTenant->id, 'name' => 'Foreign']);
        $foreignAccount = Account::withoutGlobalScopes()->create([
            'tenant_id' => $this->otherTenant->id,
            'code' => '9999',
            'name' => 'Foreign AR',
            'type' => AccountType::Asset,
            'normal_balance' => 'debit',
        ]);

        $this->post(route('invoices.store'), [
            'client_id' => $foreignClient->id,
            'invoice_number' => 'INV-X',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'receivable_account_id' => $foreignAccount->id,
            'lines' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 10, 'account_id' => $foreignAccount->id]],
        ])->assertSessionHasErrors(['client_id', 'receivable_account_id', 'lines.0.account_id']);
    }

    public function test_export_writes_formula_like_strings_as_text(): void
    {
        $response = ExcelExport::download('t.xlsx', ['Name'], [['=HYPERLINK("http://evil")'], ['+1'], ['-1+1'], ['@SUM(A1)'], ['safe']]);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'xl');
        file_put_contents($path, $content);
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        foreach (['A2', 'A3', 'A4', 'A5'] as $cell) {
            $this->assertSame('s', $sheet->getCell($cell)->getDataType());
        }
        $this->assertSame('=HYPERLINK("http://evil")', $sheet->getCell('A2')->getValue());
    }
}
