<?php

namespace Tests\Feature;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\AccountType;
use App\Enums\JournalSourceType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\CostCenter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\JournalService;
use App\Services\ReportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $bank;

    private Account $sales;

    private Account $rent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->bank = $this->account('1000', 'Bank', AccountType::Asset, 'debit');
        $this->sales = $this->account('4000', 'Sales', AccountType::Revenue, 'credit');
        $this->rent = $this->account('5000', 'Rent', AccountType::Expense, 'debit');
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

    /**
     * @param  array<int, int|float>  $months  Month number => amount; unspecified months are zero.
     * @return array{account_id: int, cost_center_id: ?int, months: array<int, int|float>}
     */
    private function line(Account $account, array $months, ?int $costCenterId = null): array
    {
        return ['account_id' => $account->id, 'cost_center_id' => $costCenterId, 'months' => $months];
    }

    private function spend(string $date, float $amount, ?int $costCenterId = null): void
    {
        $this->actingAs($this->user);

        app(JournalService::class)->postJournalEntry(new CreateJournalEntryData(
            date: $date,
            description: 'Rent',
            reference: null,
            sourceType: JournalSourceType::Manual,
            sourceId: null,
            createdBy: $this->user->id,
            lines: [
                new JournalLineData(accountId: $this->rent->id, debit: $amount, credit: 0, costCenterId: $costCenterId),
                new JournalLineData(accountId: $this->bank->id, debit: 0, credit: $amount),
            ],
        ));
    }

    public function test_budget_against_actual_shows_variance_percent_and_health(): void
    {
        $this->actingAs($this->user);
        app(BudgetService::class)->save(2026, [
            $this->line($this->rent, [1 => 100, 2 => 100]),
            $this->line($this->sales, [1 => 500]),
        ]);

        $this->spend('2026-01-10', 150);

        $report = app(BudgetService::class)->budgetVsActual('2026-01-01', '2026-01-31');

        $this->assertTrue($report['has_budget']);
        $rent = collect($report['expenses'])->firstWhere('account_id', $this->rent->id);
        $this->assertEquals(100, $rent['budget']);
        $this->assertEquals(150, $rent['actual']);
        $this->assertEquals(-50, $rent['variance']);
        $this->assertEquals(150.0, $rent['percent_used']);
        $this->assertSame('bad', $rent['health']);

        $sales = collect($report['revenue'])->firstWhere('account_id', $this->sales->id);
        $this->assertEquals(500, $sales['budget']);
        $this->assertEquals(-500, $sales['variance']);
        $this->assertSame('bad', $sales['health']);

        $this->assertEquals(400, $report['net_profit']['budget']);
        $this->assertEquals(-150, $report['net_profit']['actual']);
    }

    public function test_a_partial_month_counts_for_its_share_of_days_and_a_range_spans_months_and_years(): void
    {
        $this->actingAs($this->user);
        app(BudgetService::class)->save(2025, [$this->line($this->rent, [12 => 310])]);
        app(BudgetService::class)->save(2026, [$this->line($this->rent, [1 => 310])]);

        $service = app(BudgetService::class);

        $this->assertEquals(160, $service->budgetByAccount('2026-01-16', '2026-01-31')[$this->rent->id]['budget']);
        $this->assertEquals(620, $service->budgetByAccount('2025-12-01', '2026-01-31')[$this->rent->id]['budget']);
        $this->assertEquals(310, $service->budgetByAccount('2025-12-16', '2026-01-15')[$this->rent->id]['budget']);
    }

    public function test_cost_center_budgets_can_be_filtered_and_added_up(): void
    {
        $this->actingAs($this->user);
        $center = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Cairo', 'type' => 'cost']);

        $service = app(BudgetService::class);
        $service->save(2026, [
            $this->line($this->rent, [1 => 60], $center->id),
            $this->line($this->rent, [1 => 40]),
        ]);

        $this->assertEquals(100, $service->budgetByAccount('2026-01-01', '2026-01-31')[$this->rent->id]['budget']);
        $this->assertEquals(60, $service->budgetByAccount('2026-01-01', '2026-01-31', $center->id)[$this->rent->id]['budget']);
        $this->assertEquals(40, $service->budgetByAccount('2026-01-01', '2026-01-31', 'unassigned')[$this->rent->id]['budget']);

        $this->spend('2026-01-05', 70, $center->id);
        $cairo = $service->budgetVsActual('2026-01-01', '2026-01-31', $center->id);
        $this->assertEquals(70, $cairo['total_expenses']['actual']);
        $this->assertEquals(60, $cairo['total_expenses']['budget']);
    }

    public function test_saving_replaces_the_year_and_merges_a_duplicate_account_line(): void
    {
        $this->actingAs($this->user);

        $this->put(route('budgets.update', 2026), ['lines' => [
            $this->line($this->rent, [1 => 100]),
            $this->line($this->rent, [1 => 20, 2 => 5]),
        ]])->assertSessionHasNoErrors();

        $budget = Budget::with('lines')->where('year', 2026)->firstOrFail();
        $this->assertCount(1, $budget->lines);
        $this->assertEquals(120, $budget->lines->first()->m1);
        $this->assertEquals(5, $budget->lines->first()->m2);

        $this->put(route('budgets.update', 2026), ['lines' => []])->assertSessionHasNoErrors();
        $this->assertCount(0, $budget->fresh()->lines);
    }

    public function test_only_revenue_and_expense_accounts_with_non_negative_amounts_can_be_budgeted(): void
    {
        $this->actingAs($this->user)
            ->put(route('budgets.update', 2026), ['lines' => [$this->line($this->bank, [1 => 100])]])
            ->assertSessionHasErrors('lines.0.account_id');

        $this->actingAs($this->user)
            ->put(route('budgets.update', 2026), ['lines' => [$this->line($this->rent, [1 => -5])]])
            ->assertSessionHasErrors('lines.0.months.1');

        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_the_pages_load_and_a_viewer_cannot_change_a_budget(): void
    {
        $this->actingAs($this->user)->get(route('budgets.index'))->assertOk();
        $this->actingAs($this->user)->get(route('budgets.edit', 2026))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/Budgets/Edit')->where('budget.year', 2026));
        $this->actingAs($this->user)->get(route('reports.budget-vs-actual'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/Reports/BudgetVsActual'));

        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)->get(route('budgets.index'))->assertOk();
        $this->actingAs($viewer)->put(route('budgets.update', 2026), ['lines' => []])->assertForbidden();
        $this->actingAs($viewer)->delete(route('budgets.destroy', 2026))->assertForbidden();
    }

    public function test_the_profit_and_loss_can_carry_budget_and_variance_columns(): void
    {
        $this->actingAs($this->user);
        app(BudgetService::class)->save(2026, [$this->line($this->rent, [1 => 100]), $this->line($this->sales, [1 => 300])]);
        $this->spend('2026-01-10', 130);

        $report = app(BudgetService::class)->annotateProfitAndLoss(
            app(ReportService::class)->profitAndLoss('2026-01-01', '2026-01-31'),
        );

        $rent = collect($report['expenses'])->firstWhere('account_id', $this->rent->id);
        $this->assertEquals(100, $rent['budget']);
        $this->assertEquals(-30, $rent['variance']);

        // A budgeted account with no activity yet still appears.
        $this->assertEquals(300, collect($report['revenue'])->firstWhere('account_id', $this->sales->id)['budget']);
        $this->assertEquals(200, $report['net_profit']['budget']);
    }

    private function budgetCsv(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('budget.csv', $contents);
    }

    public function test_the_import_template_lists_budgetable_accounts_only(): void
    {
        $response = $this->actingAs($this->user)->get(route('budgets.import-template'));

        $response->assertOk()->assertDownload('budget-import-template.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $codes = collect(IOFactory::load($path)->getActiveSheet()->toArray())->pluck(0)->slice(2)->values()->all();
        unlink($path);

        $this->assertSame(['4000', '5000'], $codes);
    }

    public function test_an_uploaded_sheet_fills_the_page_for_review_without_saving(): void
    {
        $csv = "Code,Account,Applies to,Jan,Feb,Mar\n4000,Sales,,1000,\"1,200\",\n5000,Rent,,300,300,300\n";

        $response = $this->actingAs($this->user)->post(route('budgets.import', 2026), ['file' => $this->budgetCsv($csv)]);

        $response->assertRedirect(route('budgets.edit', 2026));
        $this->assertDatabaseCount('budgets', 0);

        $this->actingAs($this->user)->followingRedirects()->post(route('budgets.import', 2026), ['file' => $this->budgetCsv($csv)])
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/Budgets/Edit')
                ->has('imported', 2)
                ->where('imported.0.account_id', $this->sales->id)
                ->where('imported.0.months.2', 1200)
                ->where('imported.1.months.3', 300));
    }

    public function test_an_import_maps_cost_center_names_and_skips_rows_with_no_amounts(): void
    {
        $center = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Main Hall', 'type' => 'profit', 'is_active' => true]);
        $csv = "Code,Applies to,Jan\n4000,Main Hall,500\n5000,,\n";

        $this->actingAs($this->user)->followingRedirects()->post(route('budgets.import', 2026), ['file' => $this->budgetCsv($csv)])
            ->assertInertia(fn ($page) => $page->has('imported', 1)->where('imported.0.cost_center_id', $center->id));
    }

    public function test_an_import_reports_every_problem_and_changes_nothing(): void
    {
        $csv = "Code,Applies to,Jan\n9999,,100\n1000,,50\n4000,Nowhere,70\n";

        $response = $this->actingAs($this->user)->post(route('budgets.import', 2026), ['file' => $this->budgetCsv($csv)]);

        $response->assertSessionHasErrors('file');
        $message = session('errors')->first('file');
        $this->assertStringContainsString('9999', $message);
        $this->assertStringContainsString('not a revenue or expense account', $message);
        $this->assertStringContainsString('Nowhere', $message);
        $this->assertDatabaseCount('budgets', 0);
    }

    public function test_an_import_rejects_bad_amounts_and_a_missing_code_column(): void
    {
        $this->actingAs($this->user)->post(route('budgets.import', 2026), ['file' => $this->budgetCsv("Code,Jan\n4000,abc\n")])
            ->assertSessionHasErrors('file');
        $this->actingAs($this->user)->post(route('budgets.import', 2026), ['file' => $this->budgetCsv("Account,Jan\nSales,5\n")])
            ->assertSessionHasErrors('file');
    }

    public function test_a_saved_budget_exports_in_the_import_layout(): void
    {
        $this->actingAs($this->user)->put(route('budgets.update', 2026), ['lines' => [$this->line($this->sales, [1 => 100, 2 => 50])]]);

        $response = $this->actingAs($this->user)->get(route('budgets.export', 2026));

        $response->assertOk()->assertDownload('budget-2026.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'bud').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);

        $this->assertSame(['Code', 'Account', 'Applies to'], array_slice($rows[1], 0, 3));
        $this->assertEquals(4000, $rows[2][0]);
        $this->assertEquals(100, $rows[2][3]);
        $this->assertEquals(150, $rows[2][15]);
    }

    public function test_a_viewer_cannot_import_a_budget(): void
    {
        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)->post(route('budgets.import', 2026), ['file' => $this->budgetCsv("Code,Jan\n4000,5\n")])->assertForbidden();
        $this->actingAs($viewer)->get(route('budgets.import-template'))->assertOk();
    }

    public function test_the_workbooks_offer_the_cost_centers_as_an_applies_to_dropdown(): void
    {
        CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Main Hall', 'type' => 'profit', 'is_active' => true]);
        CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Old Wing', 'type' => 'cost', 'is_active' => false]);
        $this->actingAs($this->user)->put(route('budgets.update', 2026), ['lines' => [$this->line($this->sales, [1 => 100])]]);

        foreach ([route('budgets.import-template'), route('budgets.export', 2026)] as $url) {
            $path = tempnam(sys_get_temp_dir(), 'dd').'.xlsx';
            file_put_contents($path, $this->actingAs($this->user)->get($url)->streamedContent());
            $workbook = IOFactory::load($path);
            unlink($path);

            $validation = $workbook->getSheetByName('Budget')->getDataValidation('C3');
            $this->assertSame('list', $validation->getType());
            $this->assertTrue($validation->getAllowBlank());
            $this->assertTrue($validation->getShowDropDown(), 'the in-cell dropdown arrow must be shown');
            $this->assertStringContainsString('Cost centers', $validation->getFormula1());
            $this->assertSame([['Main Hall']], $workbook->getSheetByName('Cost centers')->toArray());
            $this->assertSame(Worksheet::SHEETSTATE_HIDDEN, $workbook->getSheetByName('Cost centers')->getSheetState());
        }
    }

    public function test_the_workbooks_start_with_a_row_explaining_the_data_and_columns(): void
    {
        $this->actingAs($this->user)->put(route('budgets.update', 2026), ['lines' => [$this->line($this->sales, [1 => 100])]]);

        foreach ([route('budgets.import-template'), route('budgets.export', 2026)] as $url) {
            $path = tempnam(sys_get_temp_dir(), 'cm').'.xlsx';
            file_put_contents($path, $this->actingAs($this->user)->get($url)->streamedContent());
            $sheet = IOFactory::load($path)->getSheetByName('Budget');
            unlink($path);

            $explanation = $sheet->getCell('A1')->getValue();
            $this->assertStringContainsString('yearly budget', $explanation);
            $this->assertStringContainsString('Code (required)', $explanation);
            $this->assertStringContainsString('Applies to', $explanation);
            $this->assertStringContainsString('Jan – Dec', $explanation);
            $this->assertContains('A1:O1', array_map(fn ($range) => str_replace('P1', 'O1', $range), $sheet->getMergeCells()));
            $this->assertSame('Code', $sheet->getCell('A2')->getValue());
        }
    }

    public function test_a_filled_in_template_with_the_explanation_row_imports_with_correct_row_numbers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        file_put_contents($path, $this->actingAs($this->user)->get(route('budgets.import-template'))->streamedContent());
        $workbook = IOFactory::load($path);
        $sheet = $workbook->getSheetByName('Budget');
        $sheet->setCellValue('D3', 1500);
        $sheet->setCellValue('D4', 'oops');
        (new Xlsx($workbook))->save($path);

        $upload = new UploadedFile($path, 'budget.xlsx', null, null, true);
        $this->actingAs($this->user)->post(route('budgets.import', 2026), ['file' => $upload])->assertSessionHasErrors('file');
        $this->assertStringContainsString('Row 4', session('errors')->first('file'));

        $sheet->setCellValue('D4', 200);
        (new Xlsx($workbook))->save($path);
        $upload = new UploadedFile($path, 'budget.xlsx', null, null, true);

        $this->actingAs($this->user)->followingRedirects()->post(route('budgets.import', 2026), ['file' => $upload])
            ->assertInertia(fn ($page) => $page->has('imported', 2)->where('imported.0.months.1', 1500)->where('imported.1.months.1', 200));
        unlink($path);
    }
}
