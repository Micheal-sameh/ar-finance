<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\GlMapping;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlMappingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Account $receivable;

    private Account $sales;

    private Account $rent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Super Admin');

        $this->receivable = $this->account($this->tenant, '1200', 'Receivables', AccountType::Asset);
        $this->sales = $this->account($this->tenant, '4000', 'Sales', AccountType::Revenue);
        $this->rent = $this->account($this->tenant, '5000', 'Rent', AccountType::Expense);
    }

    private function account(Tenant $tenant, string $code, string $name, AccountType $type): Account
    {
        return Account::create([
            'tenant_id' => $tenant->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $type->defaultNormalBalance(),
        ]);
    }

    public function test_mapping_is_saved_and_prefills_the_invoice_form(): void
    {
        $this->actingAs($this->user)
            ->put(route('gl-mapping.update'), ['mappings' => [
                'receivable' => $this->receivable->id,
                'revenue' => $this->sales->id,
            ]])
            ->assertSessionHas('success');

        $this->assertSame($this->receivable->id, GlMapping::where('role', 'receivable')->value('account_id'));

        $this->actingAs($this->user)->get(route('gl-mapping.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/GlMapping/Index')
                ->where('rows', fn ($rows) => collect($rows)->firstWhere('role', 'receivable')['account_id'] === $this->receivable->id));

        $this->actingAs($this->user)->get(route('invoices.create'))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('glDefaults.receivable', $this->receivable->id)
                ->where('glDefaults.revenue', $this->sales->id)
                ->where('glDefaults.payable', null));
    }

    public function test_booking_roles_prefill_the_bookable_item_form(): void
    {
        $deferred = $this->account($this->tenant, '2300', 'Deferred revenue', AccountType::Liability);
        $bank = $this->account($this->tenant, '1000', 'Bank', AccountType::Asset);

        $this->actingAs($this->user)->put(route('gl-mapping.update'), ['mappings' => [
            'booking_revenue' => $this->sales->id,
            'booking_deferred_revenue' => $deferred->id,
            'booking_deposit_payment' => $bank->id,
        ]])->assertSessionHas('success');

        $this->actingAs($this->user)->get(route('bookable-items.index'))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('glDefaults.booking_revenue', $this->sales->id)
                ->where('glDefaults.booking_deferred_revenue', $deferred->id)
                ->where('glDefaults.booking_deposit_payment', $bank->id));

        $this->actingAs($this->user)
            ->put(route('gl-mapping.update'), ['mappings' => ['booking_deferred_revenue' => $this->sales->id]])
            ->assertSessionHas('error');
    }

    public function test_saving_again_replaces_and_null_clears_a_role(): void
    {
        $other = $this->account($this->tenant, '1210', 'Other receivables', AccountType::Asset);

        $this->actingAs($this->user)->put(route('gl-mapping.update'), ['mappings' => ['receivable' => $this->receivable->id]]);
        $this->actingAs($this->user)->put(route('gl-mapping.update'), ['mappings' => ['receivable' => $other->id, 'revenue' => $this->sales->id]]);

        $this->assertSame(1, GlMapping::where('role', 'receivable')->count());
        $this->assertSame($other->id, GlMapping::where('role', 'receivable')->value('account_id'));

        $this->actingAs($this->user)->put(route('gl-mapping.update'), ['mappings' => ['receivable' => null]]);

        $this->assertDatabaseMissing('gl_mappings', ['role' => 'receivable']);
        $this->assertDatabaseHas('gl_mappings', ['role' => 'revenue']);
    }

    public function test_an_account_of_the_wrong_type_is_rejected_and_nothing_is_saved(): void
    {
        $this->actingAs($this->user)
            ->put(route('gl-mapping.update'), ['mappings' => [
                'receivable' => $this->receivable->id,
                'payable' => $this->rent->id,
            ]])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('gl_mappings', 0);
    }

    public function test_an_inactive_account_is_rejected(): void
    {
        $this->receivable->update(['is_active' => false]);

        $this->actingAs($this->user)
            ->put(route('gl-mapping.update'), ['mappings' => ['receivable' => $this->receivable->id]])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('gl_mappings', 0);
    }

    public function test_a_parent_account_is_rejected(): void
    {
        Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1210',
            'name' => 'Trade receivables',
            'type' => AccountType::Asset,
            'normal_balance' => AccountType::Asset->defaultNormalBalance(),
            'parent_id' => $this->receivable->id,
        ]);

        $this->actingAs($this->user)
            ->put(route('gl-mapping.update'), ['mappings' => ['receivable' => $this->receivable->id]])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('gl_mappings', 0);
    }

    public function test_an_account_from_another_tenant_is_rejected(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other Co', 'slug' => 'other-co']);
        $foreign = $this->account($otherTenant, '1200', 'Receivables', AccountType::Asset);

        $this->actingAs($this->user)
            ->put(route('gl-mapping.update'), ['mappings' => ['receivable' => $foreign->id]])
            ->assertSessionHasErrors('mappings.receivable');

        $this->assertDatabaseCount('gl_mappings', 0);
    }

    public function test_mappings_are_not_shared_between_tenants(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other Co', 'slug' => 'other-co']);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser->assignRole('Super Admin');

        $this->actingAs($this->user)->put(route('gl-mapping.update'), ['mappings' => ['receivable' => $this->receivable->id]]);

        $this->actingAs($otherUser)->get(route('invoices.create'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('glDefaults.receivable', null));
    }

    public function test_a_viewer_can_see_but_not_change_the_mapping(): void
    {
        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)->get(route('gl-mapping.index'))->assertOk();
        $this->actingAs($viewer)
            ->put(route('gl-mapping.update'), ['mappings' => ['receivable' => $this->receivable->id]])
            ->assertForbidden();

        $this->assertDatabaseCount('gl_mappings', 0);
    }

    public function test_deleting_a_mapped_account_removes_its_mapping(): void
    {
        $this->actingAs($this->user)->put(route('gl-mapping.update'), ['mappings' => ['receivable' => $this->receivable->id]]);

        $this->receivable->delete();

        $this->assertDatabaseCount('gl_mappings', 0);
    }
}
