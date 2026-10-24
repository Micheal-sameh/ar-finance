<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CostCenterTest extends TestCase
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

    public function test_can_create_a_cost_center_with_a_budget(): void
    {
        $response = $this->actingAs($this->user)->post(route('cost-centers.store'), [
            'name' => 'Marketing',
            'type' => 'cost',
            'budget' => 5000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cost_centers', ['name' => 'Marketing', 'type' => 'cost', 'budget' => 5000]);
    }

    public function test_cost_center_with_tagged_expenses_cannot_be_deleted(): void
    {
        $center = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Ops', 'type' => 'cost']);
        $expenseAccount = $this->account('6000', 'Ops Expense', AccountType::Expense, 'debit');
        $ap = $this->account('2000', 'Accounts Payable', AccountType::Liability, 'credit');

        $this->actingAs($this->user)->post(route('expenses.store'), [
            'description' => 'Team lunch',
            'account_id' => $expenseAccount->id,
            'amount' => 50,
            'date' => now()->toDateString(),
            'payable_account_id' => $ap->id,
            'cost_center_id' => $center->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('cost-centers.destroy', $center));

        $response->assertRedirect();
        $this->assertDatabaseHas('cost_centers', ['id' => $center->id]);
    }

    public function test_budget_vs_actual_summary_reflects_tagged_journal_activity(): void
    {
        $center = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Marketing', 'type' => 'cost', 'budget' => 1000]);
        $expenseAccount = $this->account('6000', 'Ad Spend', AccountType::Expense, 'debit');
        $ap = $this->account('2000', 'Accounts Payable', AccountType::Liability, 'credit');

        $this->actingAs($this->user)->post(route('expenses.store'), [
            'description' => 'Facebook ads',
            'account_id' => $expenseAccount->id,
            'amount' => 300,
            'date' => now()->toDateString(),
            'payable_account_id' => $ap->id,
            'cost_center_id' => $center->id,
        ]);
        $expenseId = Expense::where('description', 'Facebook ads')->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('expenses.approve', $expenseId));

        $response = $this->actingAs($this->user)->get(route('cost-centers.index', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/CostCenters/Index')
            ->where('summary.0.cost_center_id', $center->id)
            ->where('summary.0.budget', 1000)
            ->where('summary.0.spent', 300)
            ->where('summary.0.utilization_percent', 30)
        );
    }

    public function test_journal_entry_line_can_be_tagged_with_a_cost_center(): void
    {
        $center = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'R&D', 'type' => 'cost']);
        $cash = $this->account('1000', 'Cash', AccountType::Asset, 'debit');
        $expense = $this->account('6100', 'Software', AccountType::Expense, 'debit');

        $response = $this->actingAs($this->user)->post(route('journals.store'), [
            'date' => now()->toDateString(),
            'description' => 'Software subscription',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 99, 'credit' => 0, 'cost_center_id' => $center->id],
                ['account_id' => $expense->id, 'debit' => 0, 'credit' => 99, 'cost_center_id' => $center->id],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('journal_lines', ['account_id' => $expense->id, 'cost_center_id' => $center->id, 'credit' => 99]);
    }

    private function csvFile(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('centers.csv', $contents);
    }

    public function test_import_template_downloads_an_xlsx_workbook(): void
    {
        $response = $this->actingAs($this->user)->get(route('cost-centers.import-template'));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=cost-centers-import-template.xlsx');
    }

    public function test_importing_centers_creates_them_with_parents_resolved_in_any_order(): void
    {
        $csv = "name,type,parent,budget,is_active\n"
            ."Kitchen,cost,Operations,1500.50,true\n"
            ."Operations,Cost Center,,,\n"
            ."Catering,profit,,,no\n";

        $this->actingAs($this->user)->post(route('cost-centers.import'), ['file' => $this->csvFile($csv)])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Imported 3 center(s).');

        $operations = CostCenter::where('name', 'Operations')->firstOrFail();
        $kitchen = CostCenter::where('name', 'Kitchen')->firstOrFail();

        $this->assertSame($operations->id, $kitchen->parent_id);
        $this->assertEquals(1500.50, (float) $kitchen->budget);
        $this->assertTrue($kitchen->is_active);
        $this->assertFalse(CostCenter::where('name', 'Catering')->firstOrFail()->is_active);
        $this->assertSame('profit', CostCenter::where('name', 'Catering')->firstOrFail()->type->value);
    }

    public function test_importing_centers_can_use_an_existing_center_as_parent(): void
    {
        $existing = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Head Office', 'type' => 'cost', 'is_active' => false]);

        $this->actingAs($this->user)->post(route('cost-centers.import'), [
            'file' => $this->csvFile("name,type,parent\nIT,cost,head office\n"),
        ])->assertSessionHasNoErrors();

        $this->assertSame($existing->id, CostCenter::where('name', 'IT')->firstOrFail()->parent_id);
    }

    public function test_importing_centers_is_all_or_nothing_and_reports_every_bad_row(): void
    {
        CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Existing', 'type' => 'cost']);

        $csv = "name,type,parent,budget\n"
            ."Fine,cost,,\n"
            ."existing,cost,,\n"
            ."Dup,cost,,\n"
            ."Dup,profit,,\n"
            ."Orphan,cost,Nowhere,\n"
            ."Odd,sideways,,\n"
            ."Cheap,cost,,-5\n";

        $response = $this->actingAs($this->user)->post(route('cost-centers.import'), ['file' => $this->csvFile($csv)]);

        $response->assertSessionHasErrors('file');
        $message = session('errors')->first('file');

        foreach (['Row 7: "sideways"', 'Row 8: budget must be'] as $needle) {
            $this->assertStringContainsString($needle, $message);
        }

        // Structural row errors stop before anything is created.
        $this->assertDatabaseMissing('cost_centers', ['name' => 'Fine']);
    }

    public function test_importing_centers_rejects_duplicates_unknown_parents_and_loops(): void
    {
        CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Existing', 'type' => 'cost']);

        $csv = "name,type,parent\n"
            ."Fine,cost,\n"
            ."EXISTING,cost,\n"
            ."Dup,cost,\n"
            ."dup,profit,\n"
            ."Orphan,cost,Nowhere\n"
            ."Self,cost,Self\n";

        $this->actingAs($this->user)->post(route('cost-centers.import'), ['file' => $this->csvFile($csv)])
            ->assertSessionHasErrors('file');

        $message = session('errors')->first('file');
        $this->assertStringContainsString('Row 3: a center named "EXISTING" already exists.', $message);
        $this->assertStringContainsString('Row 5: "dup" appears more than once', $message);
        $this->assertStringContainsString('Row 6: parent "Nowhere" was not found.', $message);
        $this->assertStringContainsString('Row 7: a center cannot be its own parent.', $message);
        $this->assertDatabaseMissing('cost_centers', ['name' => 'Fine']);
    }

    public function test_a_parent_loop_in_the_file_is_rejected(): void
    {
        $this->actingAs($this->user)->post(route('cost-centers.import'), [
            'file' => $this->csvFile("name,type,parent\nA,cost,B\nB,cost,A\n"),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('cost_centers', 0);
    }

    public function test_a_user_without_manage_permission_cannot_import_centers(): void
    {
        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $viewer->assignRole('Booking Agent');

        $this->actingAs($viewer)->post(route('cost-centers.import'), ['file' => $this->csvFile("name,type\nX,cost\n")])
            ->assertForbidden();
    }
}
