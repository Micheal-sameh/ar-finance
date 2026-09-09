<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderAndBillTest extends TestCase
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

    private function createDraftPo(): int
    {
        $response = $this->actingAs($this->user)->post(route('purchase-orders.store'), [
            'vendor_id' => $this->vendor->id,
            'po_number' => 'PO-0001',
            'order_date' => now()->toDateString(),
            'lines' => [
                ['description' => 'Widgets', 'quantity' => 10, 'unit_price' => 25, 'account_id' => $this->expenseAccount->id],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        return \App\Models\PurchaseOrder::where('po_number', 'PO-0001')->firstOrFail()->id;
    }

    public function test_purchase_order_never_posts_to_the_ledger(): void
    {
        $poId = $this->createDraftPo();
        $this->actingAs($this->user)->post(route('purchase-orders.send', $poId));

        $this->assertDatabaseHas('purchase_orders', ['id' => $poId, 'status' => 'sent']);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_converting_po_to_bill_creates_a_draft_bill_and_closes_the_po(): void
    {
        $poId = $this->createDraftPo();

        $response = $this->actingAs($this->user)->post(route('purchase-orders.convert-to-bill', $poId), [
            'bill_number' => 'BILL-0001',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'payable_account_id' => $this->ap->id,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', ['id' => $poId, 'status' => 'closed']);
        $this->assertDatabaseHas('bills', ['bill_number' => 'BILL-0001', 'status' => 'draft', 'purchase_order_id' => $poId]);
        $this->assertDatabaseHas('bill_lines', ['description' => 'Widgets', 'unit_price' => 25, 'quantity' => 10]);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_approving_a_bill_posts_expense_against_payable(): void
    {
        $poId = $this->createDraftPo();
        $this->actingAs($this->user)->post(route('purchase-orders.convert-to-bill', $poId), [
            'bill_number' => 'BILL-0001',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'payable_account_id' => $this->ap->id,
        ]);
        $billId = \App\Models\Bill::where('bill_number', 'BILL-0001')->firstOrFail()->id;

        $response = $this->actingAs($this->user)->post(route('bills.approve', $billId));
        $response->assertRedirect();

        $this->assertDatabaseHas('bills', ['id' => $billId, 'status' => 'approved']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->expenseAccount->id, 'debit' => 250]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->ap->id, 'credit' => 250]);
    }

    public function test_marking_a_bill_paid_settles_payable_against_cash(): void
    {
        $poId = $this->createDraftPo();
        $this->actingAs($this->user)->post(route('purchase-orders.convert-to-bill', $poId), [
            'bill_number' => 'BILL-0001',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'payable_account_id' => $this->ap->id,
        ]);
        $billId = \App\Models\Bill::where('bill_number', 'BILL-0001')->firstOrFail()->id;
        $this->actingAs($this->user)->post(route('bills.approve', $billId));

        $response = $this->actingAs($this->user)->post(route('bills.mark-paid', $billId), [
            'payment_account_id' => $this->bank->id,
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('bills', ['id' => $billId, 'status' => 'paid']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->ap->id, 'debit' => 250]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bank->id, 'credit' => 250]);
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_a_closed_purchase_order_cannot_be_converted_again(): void
    {
        $poId = $this->createDraftPo();
        $this->actingAs($this->user)->post(route('purchase-orders.convert-to-bill', $poId), [
            'bill_number' => 'BILL-0001',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'payable_account_id' => $this->ap->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('purchase-orders.convert-to-bill', $poId), [
            'bill_number' => 'BILL-0002',
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'payable_account_id' => $this->ap->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bills', 1);
    }
}
