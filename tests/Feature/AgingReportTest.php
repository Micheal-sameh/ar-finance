<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgingReportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Account $ar;

    private Account $revenue;

    private Account $ap;

    private Account $expense;

    private Client $client;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co', 'base_currency' => 'EGP']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->ar = $this->account('1100', 'Accounts Receivable', AccountType::Asset, 'debit');
        $this->revenue = $this->account('4000', 'Sales Revenue', AccountType::Revenue, 'credit');
        $this->ap = $this->account('2000', 'Accounts Payable', AccountType::Liability, 'credit');
        $this->expense = $this->account('6000', 'Supplies Expense', AccountType::Expense, 'debit');

        $this->client = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Co', 'currency' => 'EGP']);
        $this->vendor = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Office Supplies Ltd']);
    }

    private function account(string $code, string $name, AccountType $type, string $normalBalance): Account
    {
        return Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'is_active' => true,
        ]);
    }

    private function sendInvoice(string $number, string $dueDate, float $amount): int
    {
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'client_id' => $this->client->id,
            'invoice_number' => $number,
            'issue_date' => '2026-01-01',
            'due_date' => $dueDate,
            'currency' => 'EGP',
            'receivable_account_id' => $this->ar->id,
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0, 'account_id' => $this->revenue->id],
            ],
        ])->assertSessionHasNoErrors();

        $id = Invoice::where('invoice_number', $number)->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('invoices.send', $id))->assertSessionHasNoErrors();

        return $id;
    }

    private function approveBill(string $number, string $dueDate, float $amount): int
    {
        $this->actingAs($this->user)->post(route('bills.store'), [
            'vendor_id' => $this->vendor->id,
            'bill_number' => $number,
            'bill_date' => '2026-01-01',
            'due_date' => $dueDate,
            'payable_account_id' => $this->ap->id,
            'lines' => [
                ['description' => 'Supplies', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0, 'account_id' => $this->expense->id],
            ],
        ])->assertSessionHasNoErrors();

        $id = Bill::where('bill_number', $number)->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('bills.approve', $id))->assertSessionHasNoErrors();

        return $id;
    }

    public function test_ar_aging_buckets_invoices_by_how_overdue_they_are(): void
    {
        // as_of = 2026-06-01
        $this->sendInvoice('INV-CUR', '2026-06-10', 100); // due in the future -> current
        $this->sendInvoice('INV-30', '2026-05-20', 200);  // 12 days overdue -> 1-30
        $this->sendInvoice('INV-90', '2026-04-01', 300);  // 61 days overdue -> 61-90
        $this->sendInvoice('INV-120', '2026-03-01', 400); // 92 days overdue -> 90+

        $response = $this->actingAs($this->user)->get(route('reports.aging', ['as_of' => '2026-06-01']));

        $response->assertInertia(fn ($page) => $page->component('Accounting/Reports/Aging'));

        $report = $response->viewData('page')['props']['arReport'];
        $this->assertCount(1, $report['rows']);
        $row = $report['rows'][0];

        $this->assertSame('Acme Co', $row['name']);
        $this->assertEquals(100, $row['current']);
        $this->assertEquals(200, $row['days_1_30']);
        $this->assertEquals(0, $row['days_31_60']);
        $this->assertEquals(300, $row['days_61_90']);
        $this->assertEquals(400, $row['days_90_plus']);
        $this->assertEquals(1000, $row['total']);
        $this->assertEquals(1000, $report['totals']['total']);
    }

    public function test_ar_aging_excludes_paid_draft_and_void_invoices(): void
    {
        $paidId = $this->sendInvoice('INV-PAID', '2026-05-01', 100);
        $bank = $this->account('1000', 'Bank', AccountType::Asset, 'debit');
        $this->actingAs($this->user)->post(route('invoices.record-payment', $paidId), [
            'payment_account_id' => $bank->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)->post(route('invoices.store'), [
            'client_id' => $this->client->id,
            'invoice_number' => 'INV-DRAFT',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-05-01',
            'currency' => 'EGP',
            'receivable_account_id' => $this->ar->id,
            'lines' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0, 'account_id' => $this->revenue->id],
            ],
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.aging', ['as_of' => '2026-06-01']));

        $this->assertCount(0, $response->viewData('page')['props']['arReport']['rows']);
    }

    public function test_ap_aging_buckets_bills_by_how_overdue_they_are(): void
    {
        $this->approveBill('BILL-CUR', '2026-06-15', 500); // not yet due
        $this->approveBill('BILL-31', '2026-05-01', 250);  // 31 days overdue -> 31-60

        $response = $this->actingAs($this->user)->get(route('reports.aging', ['as_of' => '2026-06-01']));

        $response->assertInertia(fn ($page) => $page->component('Accounting/Reports/Aging'));

        $report = $response->viewData('page')['props']['apReport'];
        $this->assertCount(1, $report['rows']);
        $row = $report['rows'][0];

        $this->assertSame('Office Supplies Ltd', $row['name']);
        $this->assertEquals(500, $row['current']);
        $this->assertEquals(250, $row['days_31_60']);
        $this->assertEquals(750, $row['total']);
    }

    public function test_ap_aging_excludes_draft_and_paid_bills(): void
    {
        $this->actingAs($this->user)->post(route('bills.store'), [
            'vendor_id' => $this->vendor->id,
            'bill_number' => 'BILL-DRAFT',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-05-01',
            'payable_account_id' => $this->ap->id,
            'lines' => [
                ['description' => 'Supplies', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0, 'account_id' => $this->expense->id],
            ],
        ]);

        $response = $this->actingAs($this->user)->get(route('reports.aging', ['as_of' => '2026-06-01']));

        $this->assertCount(0, $response->viewData('page')['props']['apReport']['rows']);
    }
}
