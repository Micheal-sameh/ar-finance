<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BillPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Vendor $vendor;

    private Account $expenseAccount;

    private Account $ap;

    private Account $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->vendor = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Supplies']);
        $this->expenseAccount = $this->account('6000', 'Supplies Expense', AccountType::Expense, 'debit');
        $this->ap = $this->account('2000', 'Accounts Payable', AccountType::Liability, 'credit');
        $this->bank = $this->account('1000', 'Bank', AccountType::Asset, 'debit');
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

    private function approvedBill(string $number, float $total, ?string $billDate = null, ?string $dueDate = null): Bill
    {
        $bill = Bill::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'bill_number' => $number,
            'bill_date' => $billDate ?? '2026-01-10',
            'due_date' => $dueDate ?? '2026-02-10',
            'status' => 'draft',
            'payable_account_id' => $this->ap->id,
        ]);
        $bill->lines()->create([
            'description' => 'Widgets',
            'quantity' => 1,
            'unit_price' => $total,
            'tax_rate' => 0,
            'account_id' => $this->expenseAccount->id,
        ]);

        $this->actingAs($this->user)->post(route('bills.approve', $bill->id));

        return $bill->refresh();
    }

    private function pay(Bill $bill, array $data = []): TestResponse
    {
        return $this->actingAs($this->user)->post(route('bills.mark-paid', $bill->id), [
            'payment_account_id' => $this->bank->id,
            ...$data,
        ]);
    }

    public function test_a_partial_payment_leaves_the_bill_partially_paid_with_a_balance(): void
    {
        $bill = $this->approvedBill('BILL-1', 250);

        $this->pay($bill, ['amount' => 100, 'paid_on' => '2026-01-20', 'reference' => 'CHQ-77'])->assertSessionMissing('error');

        $bill->refresh()->load(['lines', 'payments']);
        $this->assertSame('partially_paid', $bill->status->value);
        $this->assertNull($bill->paid_at);
        $this->assertSame(150.0, $bill->balanceDue());
        $this->assertDatabaseHas('bill_payments', ['bill_id' => $bill->id, 'amount' => 100, 'paid_on' => '2026-01-20 00:00:00', 'reference' => 'CHQ-77']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->ap->id, 'debit' => 100]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bank->id, 'credit' => 100]);
        $this->assertDatabaseHas('journal_entries', ['date' => '2026-01-20 00:00:00', 'reference' => 'CHQ-77']);
    }

    public function test_paying_the_remaining_balance_settles_the_bill(): void
    {
        $bill = $this->approvedBill('BILL-1', 250);
        $this->pay($bill, ['amount' => 100]);

        $this->pay($bill)->assertSessionMissing('error');

        $bill->refresh()->load(['lines', 'payments']);
        $this->assertSame('paid', $bill->status->value);
        $this->assertNotNull($bill->paid_at);
        $this->assertSame(0.0, $bill->balanceDue());
        $this->assertCount(2, $bill->payments);
        $this->assertDatabaseHas('bill_payments', ['bill_id' => $bill->id, 'amount' => 150]);
    }

    public function test_a_payment_cannot_exceed_the_balance_or_be_dated_before_the_bill(): void
    {
        $bill = $this->approvedBill('BILL-1', 250);

        $this->pay($bill, ['amount' => 300])->assertSessionHas('error');
        $this->pay($bill, ['amount' => 0])->assertSessionHasErrors('amount');
        $this->pay($bill, ['paid_on' => '2026-01-01'])->assertSessionHas('error');
        $this->pay($bill, ['paid_on' => now()->addDays(3)->toDateString()])->assertSessionHasErrors('paid_on');

        $this->assertDatabaseCount('bill_payments', 0);
        $this->assertSame('approved', $bill->refresh()->status->value);
    }

    public function test_a_draft_or_fully_paid_bill_cannot_be_paid(): void
    {
        $draft = Bill::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'bill_number' => 'BILL-D',
            'bill_date' => '2026-01-10',
            'due_date' => '2026-02-10',
            'status' => 'draft',
            'payable_account_id' => $this->ap->id,
        ]);
        $this->pay($draft)->assertSessionHas('error');

        $paid = $this->approvedBill('BILL-P', 80);
        $this->pay($paid);
        $this->pay($paid)->assertSessionHas('error');

        $this->assertDatabaseCount('bill_payments', 1);
    }

    public function test_pay_many_settles_each_bill_in_full_from_one_account(): void
    {
        $first = $this->approvedBill('BILL-1', 250);
        $second = $this->approvedBill('BILL-2', 90);
        $this->pay($second, ['amount' => 40]);

        $response = $this->actingAs($this->user)->post(route('bills.pay-batch'), [
            'bill_ids' => [$first->id, $second->id],
            'payment_account_id' => $this->bank->id,
            'paid_on' => '2026-01-25',
            'reference' => 'BATCH-1',
        ]);

        $response->assertSessionMissing('error');
        $this->assertSame('paid', $first->refresh()->status->value);
        $this->assertSame('paid', $second->refresh()->status->value);
        $this->assertDatabaseHas('bill_payments', ['bill_id' => $first->id, 'amount' => 250, 'reference' => 'BATCH-1']);
        $this->assertDatabaseHas('bill_payments', ['bill_id' => $second->id, 'amount' => 50, 'reference' => 'BATCH-1']);
    }

    public function test_pay_many_is_all_or_nothing(): void
    {
        $payable = $this->approvedBill('BILL-1', 250);
        $paid = $this->approvedBill('BILL-2', 90);
        $this->pay($paid);
        $paymentsBefore = BillPayment::count();

        $this->actingAs($this->user)->post(route('bills.pay-batch'), [
            'bill_ids' => [$payable->id, $paid->id],
            'payment_account_id' => $this->bank->id,
        ])->assertSessionHas('error');

        $this->assertSame($paymentsBefore, BillPayment::count());
        $this->assertSame('approved', $payable->refresh()->status->value);
    }

    public function test_ap_aging_counts_only_the_remaining_balance_of_a_partially_paid_bill(): void
    {
        $bill = $this->approvedBill('BILL-1', 250, '2026-01-10', '2026-02-01');
        $this->pay($bill, ['amount' => 100]);

        $this->actingAs($this->user)->get(route('reports.aging', ['as_of' => '2026-06-01']))
            ->assertInertia(fn ($page) => $page->where('apReport.totals.total', 150));
    }

    public function test_a_vendor_summary_reflects_partial_payments(): void
    {
        $bill = $this->approvedBill('BILL-1', 250);
        $this->pay($bill, ['amount' => 100]);

        $this->actingAs($this->user)->get(route('vendors.show', $this->vendor->id))
            ->assertInertia(fn ($page) => $page
                ->where('summary.total_billed', 250)
                ->where('summary.total_paid', 100)
                ->where('summary.outstanding', 150));
    }

    public function test_a_viewer_cannot_pay_bills(): void
    {
        $bill = $this->approvedBill('BILL-1', 250);
        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)->post(route('bills.mark-paid', $bill->id), ['payment_account_id' => $this->bank->id])->assertForbidden();
        $this->actingAs($viewer)->post(route('bills.pay-batch'), ['bill_ids' => [$bill->id], 'payment_account_id' => $this->bank->id])->assertForbidden();
    }

    public function test_another_tenant_cannot_pay_or_batch_pay_a_bill(): void
    {
        $bill = $this->approvedBill('BILL-1', 250);
        $otherTenant = Tenant::create(['name' => 'Other Co', 'slug' => 'other-co']);
        $outsider = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $outsider->assignRole('Super Admin');

        $this->actingAs($outsider)->post(route('bills.mark-paid', $bill->id), ['payment_account_id' => $this->bank->id])->assertNotFound();
        $this->actingAs($outsider)->post(route('bills.pay-batch'), ['bill_ids' => [$bill->id], 'payment_account_id' => $this->bank->id])->assertSessionHasErrors('bill_ids.0');

        $this->assertDatabaseCount('bill_payments', 0);
    }

    public function test_platform_admin_can_open_and_pay_a_bill_from_the_combined_view_by_naming_its_service(): void
    {
        $bill = $this->approvedBill('BILL-PA', 100);

        $admin = User::factory()->create(['tenant_id' => null]);
        $admin->assignRole('Platform Admin');

        $this->actingAs($admin)
            ->get(route('bills.show', ['bill' => $bill->id, 'for_tenant' => $this->tenant->id]))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('bills.mark-paid', ['bill' => $bill->id, 'for_tenant' => $this->tenant->id]), [
                'payment_account_id' => $this->bank->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('paid', $bill->refresh()->status->value);
    }
}
