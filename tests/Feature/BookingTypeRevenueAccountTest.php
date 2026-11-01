<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\CostCenter;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Booking\Models\BookableItem;
use App\Modules\Booking\Models\BookingType;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTypeRevenueAccountTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Account $itemRevenue;

    private Account $weddingRevenue;

    private Account $bank;

    private BookableItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('Admin');

        $this->itemRevenue = $this->account('4000', 'Hall revenue', AccountType::Revenue);
        $this->weddingRevenue = $this->account('4100', 'Wedding revenue', AccountType::Revenue);
        $this->bank = $this->account('1000', 'Bank', AccountType::Asset);

        $this->item = BookableItem::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Hall',
            'is_exclusive' => false,
            'account_id' => $this->itemRevenue->id,
            'cost_center_id' => CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Front Desk', 'type' => 'profit'])->id,
        ]);
    }

    private function account(string $code, string $name, AccountType $type): Account
    {
        return Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $type->defaultNormalBalance(),
        ]);
    }

    private function offer(string $name, ?int $accountId): BookingType
    {
        $type = BookingType::create(['tenant_id' => $this->tenant->id, 'name' => $name]);
        $this->item->bookingTypes()->attach($type->id, ['price' => 500, 'rate_type' => 'flat', 'account_id' => $accountId]);

        return $type;
    }

    private function bookAndPay(BookingType $type): void
    {
        $this->actingAs($this->admin)->post(route('bookings.store'), [
            'bookable_item_id' => $this->item->id,
            'booking_type_id' => $type->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ])->assertSessionHasNoErrors();

        $booking = $this->item->bookings()->latest('id')->firstOrFail();

        $this->actingAs($this->admin)->post(route('bookings.mark-paid', $booking), ['payment_account_id' => $this->bank->id])
            ->assertSessionHasNoErrors();
    }

    public function test_a_paid_booking_credits_its_booking_types_own_revenue_account(): void
    {
        $this->bookAndPay($this->offer('Wedding', $this->weddingRevenue->id));

        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->weddingRevenue->id, 'credit' => 500]);
        $this->assertDatabaseMissing('journal_lines', ['account_id' => $this->itemRevenue->id]);
    }

    public function test_a_type_without_its_own_account_falls_back_to_the_items_revenue_account(): void
    {
        $this->bookAndPay($this->offer('Meeting', null));

        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->itemRevenue->id, 'credit' => 500]);
    }

    public function test_the_item_form_saves_a_revenue_account_per_type(): void
    {
        $wedding = BookingType::create(['tenant_id' => $this->tenant->id, 'name' => 'Wedding']);
        $meeting = BookingType::create(['tenant_id' => $this->tenant->id, 'name' => 'Meeting']);

        $this->actingAs($this->admin)->put(route('bookable-items.update', $this->item), [
            'name' => 'Hall',
            'is_exclusive' => false,
            'account_id' => $this->itemRevenue->id,
            'cost_center_id' => $this->item->cost_center_id,
            'booking_types' => [
                ['booking_type_id' => $wedding->id, 'rate_type' => 'flat', 'price' => 500, 'account_id' => $this->weddingRevenue->id],
                ['booking_type_id' => $meeting->id, 'rate_type' => 'flat', 'price' => 100, 'account_id' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookable_item_booking_type', ['booking_type_id' => $wedding->id, 'account_id' => $this->weddingRevenue->id]);
        $this->assertDatabaseHas('bookable_item_booking_type', ['booking_type_id' => $meeting->id, 'account_id' => null]);

        $this->actingAs($this->admin)->get(route('bookable-items.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('items.data.0.booking_types.0.account_id', fn ($id) => in_array($id, [$this->weddingRevenue->id, null], true)));
    }

    public function test_a_booking_type_can_be_free_and_its_booking_is_paid_without_a_ledger_entry(): void
    {
        $type = BookingType::create(['tenant_id' => $this->tenant->id, 'name' => 'Open day']);

        $this->actingAs($this->admin)->put(route('bookable-items.update', $this->item), [
            'name' => 'Hall',
            'is_exclusive' => false,
            'account_id' => $this->itemRevenue->id,
            'cost_center_id' => $this->item->cost_center_id,
            'booking_types' => [['booking_type_id' => $type->id, 'rate_type' => 'flat', 'price' => 0, 'account_id' => null]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('bookings.store'), [
            'bookable_item_id' => $this->item->id,
            'booking_type_id' => $type->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ])->assertSessionHasNoErrors();

        $booking = $this->item->bookings()->firstOrFail();
        $this->assertEquals(0, $booking->price);

        $this->actingAs($this->admin)->post(route('bookings.mark-paid', $booking), ['payment_account_id' => $this->bank->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'paid']);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_a_revenue_account_from_another_tenant_is_rejected(): void
    {
        $other = Tenant::create(['name' => 'Other Co', 'slug' => 'other-co']);
        $foreign = Account::create(['tenant_id' => $other->id, 'code' => '4000', 'name' => 'Theirs', 'type' => AccountType::Revenue, 'normal_balance' => 'credit']);
        $type = BookingType::create(['tenant_id' => $this->tenant->id, 'name' => 'Wedding']);

        $this->actingAs($this->admin)->put(route('bookable-items.update', $this->item), [
            'name' => 'Hall',
            'is_exclusive' => false,
            'account_id' => $this->itemRevenue->id,
            'cost_center_id' => $this->item->cost_center_id,
            'booking_types' => [['booking_type_id' => $type->id, 'rate_type' => 'flat', 'price' => 500, 'account_id' => $foreign->id]],
        ])->assertSessionHasErrors('booking_types.0.account_id');
    }
}
