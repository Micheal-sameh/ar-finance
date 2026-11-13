<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\GlMappingRole;
use App\Models\Account;
use App\Models\CostCenter;
use App\Models\GlMapping;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Booking\Models\BookableItem;
use App\Modules\Booking\Models\BookingType;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Account $revenue;

    private Account $bank;

    private CostCenter $costCenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('Booking Agent');

        $this->revenue = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '4000',
            'name' => 'Booking Revenue',
            'type' => AccountType::Revenue,
            'normal_balance' => 'credit',
        ]);

        $this->bank = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1000',
            'name' => 'Bank',
            'type' => AccountType::Asset,
            'normal_balance' => 'debit',
        ]);

        $this->costCenter = CostCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Front Desk',
            'type' => 'profit',
        ]);

        foreach ([GlMappingRole::BookingCashPayment, GlMappingRole::BookingBankPayment, GlMappingRole::BookingPosPayment] as $role) {
            GlMapping::create(['tenant_id' => $this->tenant->id, 'role' => $role, 'account_id' => $this->bank->id]);
        }
    }

    private function item(bool $isExclusive): BookableItem
    {
        return BookableItem::create([
            'tenant_id' => $this->tenant->id,
            'name' => $isExclusive ? 'Treatment Room' : 'Group Class',
            'is_exclusive' => $isExclusive,
            'rate_type' => 'hourly',
            'price' => 100,
            'account_id' => $this->revenue->id,
            'cost_center_id' => $this->costCenter->id,
        ]);
    }

    /**
     * Reusable catalog type, offered on the item at the given price.
     */
    private function offer(BookableItem $item, string $name, float $price, string $rateType = 'hourly'): BookingType
    {
        $type = BookingType::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => $name]);
        $item->bookingTypes()->attach($type->id, ['price' => $price, 'rate_type' => $rateType]);

        return $type;
    }

    public function test_a_booking_is_priced_from_its_booking_type(): void
    {
        $item = $this->item(isExclusive: true);
        $wedding = $this->offer($item, 'Wedding Pray', 500);
        $this->offer($item, 'Holy Mass', 200);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'booking_type_id' => $wedding->id,
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 12:00:00',
        ])->assertSessionHasNoErrors();

        // Hourly item: the type's 500/hour × 2 hours, not the item's own 100.
        $this->assertDatabaseHas('bookings', ['bookable_item_id' => $item->id, 'booking_type_id' => $wedding->id, 'price' => 1000]);
    }

    public function test_the_same_type_can_have_a_different_price_on_each_item(): void
    {
        $bigger = $this->item(isExclusive: true);
        $smaller = $this->item(isExclusive: false);
        $meeting = $this->offer($bigger, 'Meeting', 300);
        $smaller->bookingTypes()->attach($meeting->id, ['price' => 40, 'rate_type' => 'flat']);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $smaller->id,
            'payment_method' => 'cash',
            'booking_type_id' => $meeting->id,
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 11:00:00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['bookable_item_id' => $smaller->id, 'price' => 40]);
    }

    public function test_a_booking_type_is_required_when_the_item_has_types(): void
    {
        $item = $this->item(isExclusive: true);
        $this->offer($item, 'Meeting', 50);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 12:00:00',
        ])->assertSessionHasErrors('booking_type_id');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_a_booking_type_not_offered_on_the_item_is_rejected(): void
    {
        $item = $this->item(isExclusive: true);
        $this->offer($item, 'Meeting', 50);
        $other = $this->item(isExclusive: false);
        $foreign = $this->offer($other, 'Class', 20);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'booking_type_id' => $foreign->id,
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 12:00:00',
        ])->assertSessionHasErrors('booking_type_id');
    }

    public function test_an_items_offered_types_and_prices_are_synced_when_saving(): void
    {
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $admin->assignRole('Admin');
        $item = $this->item(isExclusive: true);
        $keep = $this->offer($item, 'Meeting', 10);
        $drop = $this->offer($item, 'Gone', 10);
        $added = BookingType::create(['tenant_id' => $this->tenant->id, 'name' => 'Wedding Pray']);

        $this->actingAs($admin)->put(route('bookable-items.update', $item), [
            'name' => $item->name,
            'account_id' => $this->revenue->id,
            'cost_center_id' => $this->costCenter->id,
            'booking_types' => [
                ['booking_type_id' => $keep->id, 'rate_type' => 'hourly', 'price' => 75],
                ['booking_type_id' => $added->id, 'rate_type' => 'flat', 'price' => 900],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookable_item_booking_type', ['bookable_item_id' => $item->id, 'booking_type_id' => $keep->id, 'rate_type' => 'hourly', 'price' => 75]);
        $this->assertDatabaseHas('bookable_item_booking_type', ['bookable_item_id' => $item->id, 'booking_type_id' => $added->id, 'rate_type' => 'flat', 'price' => 900]);
        $this->assertDatabaseMissing('bookable_item_booking_type', ['bookable_item_id' => $item->id, 'booking_type_id' => $drop->id]);
    }

    public function test_a_bookable_item_needs_at_least_one_booking_type_and_no_item_level_price(): void
    {
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $admin->assignRole('Admin');

        $this->actingAs($admin)->post(route('bookable-items.store'), [
            'name' => 'Bigger Church',
            'account_id' => $this->revenue->id,
            'cost_center_id' => $this->costCenter->id,
        ])->assertSessionHasErrors('booking_types');

        $this->actingAs($admin)->post(route('bookable-items.store'), [
            'name' => 'Bigger Church',
            'account_id' => $this->revenue->id,
            'cost_center_id' => $this->costCenter->id,
            'booking_types' => [
                ['booking_type_id' => BookingType::create(['tenant_id' => $this->tenant->id, 'name' => 'Holy Mass'])->id, 'rate_type' => 'flat', 'price' => 200],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookable_items', ['name' => 'Bigger Church']);
    }

    public function test_a_flat_rate_type_does_not_multiply_by_hours(): void
    {
        $item = $this->item(isExclusive: true);
        $wedding = $this->offer($item, 'Wedding Pray', 500, 'flat');

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'booking_type_id' => $wedding->id,
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 14:00:00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['booking_type_id' => $wedding->id, 'price' => 500]);
    }

    public function test_a_percent_discount_reduces_the_booking_price(): void
    {
        $item = $this->item(isExclusive: true);
        $wedding = $this->offer($item, 'Wedding Pray', 500, 'flat');

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'booking_type_id' => $wedding->id,
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 14:00:00',
            'discount_type' => 'percent',
            'discount_value' => 10,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['price' => 450, 'discount_type' => 'percent', 'discount_value' => 10, 'discount_amount' => 50]);
    }

    public function test_a_fixed_discount_reduces_the_booking_price(): void
    {
        $item = $this->item(isExclusive: true);
        $wedding = $this->offer($item, 'Wedding Pray', 500, 'flat');

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'booking_type_id' => $wedding->id,
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 14:00:00',
            'discount_type' => 'fixed',
            'discount_value' => 75,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['price' => 425, 'discount_amount' => 75]);
    }

    public function test_invalid_discounts_are_rejected(): void
    {
        $item = $this->item(isExclusive: true);
        $wedding = $this->offer($item, 'Wedding Pray', 500, 'flat');
        $base = [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'booking_type_id' => $wedding->id,
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 14:00:00',
        ];

        $this->actingAs($this->user)->post(route('bookings.store'), $base + ['discount_type' => 'percent', 'discount_value' => 150])
            ->assertSessionHasErrors('discount_value');
        $this->actingAs($this->user)->post(route('bookings.store'), $base + ['discount_value' => 10])
            ->assertSessionHasErrors('discount_type');
        $this->actingAs($this->user)->post(route('bookings.store'), $base + ['discount_type' => 'fixed', 'discount_value' => 600])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_types_can_be_managed_and_a_used_one_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $admin->assignRole('Admin');

        $this->actingAs($admin)->post(route('booking-types.store'), ['name' => 'Holy Mass'])->assertSessionHasNoErrors();
        $type = BookingType::where('name', 'Holy Mass')->firstOrFail();

        $this->actingAs($admin)->post(route('booking-types.store'), ['name' => 'Holy Mass'])->assertSessionHasErrors('name');

        $this->actingAs($admin)->put(route('booking-types.update', $type), ['name' => 'Sunday Mass'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('booking_types', ['id' => $type->id, 'name' => 'Sunday Mass']);

        $this->offer($this->item(isExclusive: true), 'Sunday Mass', 10);
        $this->actingAs($admin)->delete(route('booking-types.destroy', $type))->assertSessionHas('error');
        $this->assertDatabaseHas('booking_types', ['id' => $type->id]);

        $unused = BookingType::create(['tenant_id' => $this->tenant->id, 'name' => 'Unused']);
        $this->actingAs($admin)->delete(route('booking-types.destroy', $unused))->assertSessionHas('success');
        $this->assertDatabaseMissing('booking_types', ['id' => $unused->id]);
    }

    public function test_a_weekly_booking_creates_one_booking_per_week_until_the_end_date(): void
    {
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2027-01-04 10:00:00',
            'ends_at' => '2027-01-04 12:00:00',
            'recurrence' => 'weekly',
            'recurrence_until' => '2027-01-25',
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Booked and paid 4 times.');

        $bookings = $item->bookings()->orderBy('starts_at')->get();

        $this->assertSame(
            ['2027-01-04 10:00:00', '2027-01-11 10:00:00', '2027-01-18 10:00:00', '2027-01-25 10:00:00'],
            $bookings->map(fn ($booking) => $booking->starts_at->toDateTimeString())->all(),
        );
        $this->assertSame('2027-01-25 12:00:00', $bookings->last()->ends_at->toDateTimeString());
        $this->assertCount(1, $bookings->pluck('recurrence_group_id')->unique());
    }

    public function test_a_monthly_booking_on_the_31st_does_not_drift_after_a_short_month(): void
    {
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2027-01-31 10:00:00',
            'ends_at' => '2027-01-31 11:00:00',
            'recurrence' => 'monthly',
            'recurrence_until' => '2027-03-31',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            ['2027-01-31', '2027-02-28', '2027-03-31'],
            $item->bookings()->orderBy('starts_at')->get()->map(fn ($booking) => $booking->starts_at->toDateString())->all(),
        );
    }

    public function test_a_yearly_booking_repeats_every_year(): void
    {
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2027-06-01 10:00:00',
            'ends_at' => '2027-06-01 11:00:00',
            'recurrence' => 'yearly',
            'recurrence_until' => '2029-06-01',
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, $item->bookings()->count());
    }

    public function test_a_repeating_booking_is_all_or_nothing_when_one_date_conflicts(): void
    {
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2027-01-11 10:00:00',
            'ends_at' => '2027-01-11 12:00:00',
        ]);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2027-01-04 10:00:00',
            'ends_at' => '2027-01-04 12:00:00',
            'recurrence' => 'weekly',
            'recurrence_until' => '2027-01-25',
        ])->assertSessionHas('error');

        $this->assertSame(1, $item->bookings()->count());
    }

    public function test_a_repeating_booking_needs_an_end_date(): void
    {
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2027-01-04 10:00:00',
            'ends_at' => '2027-01-04 12:00:00',
            'recurrence' => 'daily',
        ])->assertSessionHasErrors('recurrence_until');

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2027-01-04 10:00:00',
            'ends_at' => '2027-01-04 12:00:00',
            'recurrence' => 'none',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $item->bookings()->count());
    }

    public function test_item_options_load_for_a_platform_admin_viewing_all_tenants(): void
    {
        $item = $this->item(isExclusive: true);
        $platformAdmin = User::factory()->create(['tenant_id' => null]);
        $platformAdmin->assignRole('Platform Admin');

        $this->actingAs($platformAdmin)->getJson(route('bookable-items.options'))
            ->assertOk()
            ->assertJsonPath('0.id', $item->id)
            ->assertJsonPath('0.tenant_name', $this->tenant->name);
    }

    public function test_calendar_feed_returns_a_plain_array_not_a_data_envelope(): void
    {
        // This app disables JsonResource's default {"data": [...]} wrapping
        // app-wide (see JsonResource::withoutWrapping() in AppServiceProvider),
        // so the frontend reads the response body directly as the array.
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('bookings.calendar', [
            'from' => '2026-10-01',
            'to' => '2026-10-20',
        ]));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonMissingPath('0.data');
    }

    public function test_booking_an_exclusive_item_prices_it_from_the_hourly_rate(): void
    {
        $item = $this->item(isExclusive: true);

        $response = $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'price' => 200.00,
            'status' => 'paid',
        ]);
    }

    public function test_an_exclusive_item_cannot_be_double_booked_for_an_overlapping_slot(): void
    {
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ]);

        $second = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $second->assignRole('Booking Agent');

        $response = $this->actingAs($second)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2026-10-10 11:00:00',
            'ends_at' => '2026-10-10 13:00:00',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_a_shared_item_can_be_booked_concurrently_by_different_users(): void
    {
        $item = $this->item(isExclusive: false);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 11:00:00',
        ]);

        $second = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $second->assignRole('Booking Agent');

        $response = $this->actingAs($second)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 11:00:00',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_a_booking_is_paid_when_made_and_posts_revenue_against_the_items_account_and_center(): void
    {
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', ['bookable_item_id' => $item->id, 'status' => 'paid']);
        $this->assertNotNull($item->bookings()->firstOrFail()->paid_at);
        $this->assertDatabaseHas('journal_lines', [
            'account_id' => $this->bank->id,
            'debit' => 200.00,
            'cost_center_id' => $this->costCenter->id,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'account_id' => $this->revenue->id,
            'credit' => 200.00,
            'cost_center_id' => $this->costCenter->id,
        ]);
    }

    public function test_a_booking_types_own_cost_center_is_used_when_posting(): void
    {
        $item = $this->item(isExclusive: true);
        $training = $this->offer($item, 'Training', 100);
        $other = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Academy', 'type' => 'profit']);
        $item->bookingTypes()->updateExistingPivot($training->id, ['cost_center_id' => $other->id]);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'booking_type_id' => $training->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->revenue->id, 'credit' => 200.00, 'cost_center_id' => $other->id]);
        $this->assertDatabaseMissing('journal_lines', ['cost_center_id' => $this->costCenter->id]);
    }

    public function test_a_booking_is_refused_when_no_payment_account_is_mapped(): void
    {
        GlMapping::query()->delete();
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 12:00:00',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_each_payment_method_posts_to_its_own_mapped_account(): void
    {
        $cashbox = Account::create(['tenant_id' => $this->tenant->id, 'code' => '1010', 'name' => 'Cash on Hand', 'type' => AccountType::Asset, 'normal_balance' => 'debit']);
        GlMapping::where('role', GlMappingRole::BookingCashPayment)->update(['account_id' => $cashbox->id]);
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 12:00:00',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'bank_transfer',
            'starts_at' => '2027-01-11 10:00:00',
            'ends_at' => '2027-01-11 12:00:00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['starts_at' => '2027-01-10 10:00:00', 'payment_method' => 'cash']);
        $this->assertDatabaseHas('bookings', ['starts_at' => '2027-01-11 10:00:00', 'payment_method' => 'bank_transfer']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $cashbox->id, 'debit' => 200.00]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bank->id, 'debit' => 200.00]);
        $this->assertDatabaseCount('journal_lines', 4);
    }

    public function test_a_pos_payment_posts_to_the_pos_account(): void
    {
        $posAccount = Account::create(['tenant_id' => $this->tenant->id, 'code' => '1020', 'name' => 'POS Clearing', 'type' => AccountType::Asset, 'normal_balance' => 'debit']);
        GlMapping::where('role', GlMappingRole::BookingPosPayment)->update(['account_id' => $posAccount->id]);
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'pos',
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 12:00:00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['payment_method' => 'pos']);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $posAccount->id, 'debit' => 200.00]);
    }

    public function test_a_payment_method_is_required_and_must_be_valid(): void
    {
        $item = $this->item(isExclusive: true);
        $base = ['bookable_item_id' => $item->id, 'starts_at' => '2027-01-10 10:00:00', 'ends_at' => '2027-01-10 12:00:00'];

        $this->actingAs($this->user)->post(route('bookings.store'), $base)->assertSessionHasErrors('payment_method');
        $this->actingAs($this->user)->post(route('bookings.store'), $base + ['payment_method' => 'cheque'])->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_a_method_with_no_mapped_account_is_refused_while_the_other_still_works(): void
    {
        GlMapping::where('role', GlMappingRole::BookingCashPayment)->delete();
        $item = $this->item(isExclusive: true);
        $base = ['bookable_item_id' => $item->id, 'ends_at' => '2027-01-10 12:00:00'];

        $this->actingAs($this->user)->post(route('bookings.store'), $base + ['payment_method' => 'cash', 'starts_at' => '2027-01-10 10:00:00'])
            ->assertSessionHas('error');
        $this->assertDatabaseCount('bookings', 0);

        $this->actingAs($this->user)->post(route('bookings.store'), $base + ['payment_method' => 'bank_transfer', 'starts_at' => '2027-01-10 10:00:00'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_a_free_booking_is_paid_without_touching_the_ledger(): void
    {
        $item = $this->item(isExclusive: true);
        $type = $this->offer($item, 'Open Day', 0);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'booking_type_id' => $type->id,
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 12:00:00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['bookable_item_id' => $item->id, 'status' => 'paid']);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_a_booking_admin_saves_an_item_without_touching_its_accounting(): void
    {
        $item = $this->item(isExclusive: true);
        $type = $this->offer($item, 'Training', 100);
        $other = CostCenter::create(['tenant_id' => $this->tenant->id, 'name' => 'Academy', 'type' => 'profit']);
        $item->bookingTypes()->updateExistingPivot($type->id, ['account_id' => $this->revenue->id, 'cost_center_id' => $other->id]);

        $bookingAdmin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $bookingAdmin->assignRole('Booking Admin');

        $this->actingAs($bookingAdmin)->put(route('bookable-items.update', $item), [
            'name' => 'Renamed Room',
            'account_id' => $this->bank->id,
            'cost_center_id' => $other->id,
            'booking_types' => [['booking_type_id' => $type->id, 'rate_type' => 'hourly', 'price' => 150, 'account_id' => $this->bank->id, 'cost_center_id' => null]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookable_items', ['id' => $item->id, 'name' => 'Renamed Room', 'account_id' => $this->revenue->id, 'cost_center_id' => $this->costCenter->id]);
        $this->assertDatabaseHas('bookable_item_booking_type', ['bookable_item_id' => $item->id, 'price' => 150, 'account_id' => $this->revenue->id, 'cost_center_id' => $other->id]);
    }

    public function test_an_item_created_by_a_booking_admin_has_no_cost_center_and_cannot_be_booked_without_a_revenue_account(): void
    {
        $bookingAdmin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $bookingAdmin->assignRole('Booking Admin');
        $type = BookingType::create(['tenant_id' => $this->tenant->id, 'name' => 'Holy Mass']);

        $this->actingAs($bookingAdmin)->post(route('bookable-items.store'), [
            'name' => 'Side Chapel',
            'booking_types' => [['booking_type_id' => $type->id, 'rate_type' => 'flat', 'price' => 200]],
        ])->assertSessionHasNoErrors();

        $item = BookableItem::where('name', 'Side Chapel')->firstOrFail();
        $this->assertNull($item->cost_center_id);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'booking_type_id' => $type->id,
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 12:00:00',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_cancelling_a_paid_booking_reverses_its_journal_at_the_same_accounts_and_cost_center(): void
    {
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 12:00:00',
        ])->assertSessionHasNoErrors();

        $booking = $item->bookings()->firstOrFail();
        $manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $manager->assignRole('Admin');

        $this->actingAs($manager)->post(route('bookings.cancel', $booking))->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bank->id, 'credit' => 200.00, 'cost_center_id' => $this->costCenter->id]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->revenue->id, 'debit' => 200.00, 'cost_center_id' => $this->costCenter->id]);
        $this->assertEquals(0.0, (float) DB::table('journal_lines')->where('account_id', $this->revenue->id)->selectRaw('sum(credit) - sum(debit) as net')->value('net'));
    }

    public function test_a_booking_cannot_be_cancelled_twice(): void
    {
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'payment_method' => 'cash',
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 12:00:00',
        ]);

        $booking = $item->bookings()->firstOrFail();

        $this->actingAs($this->user)->post(route('bookings.cancel', $booking))->assertSessionHas('success');
        $this->actingAs($this->user)->post(route('bookings.cancel', $booking))->assertSessionHas('error');

        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_a_booking_agent_is_redirected_from_the_dashboard_to_the_calendar(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertRedirect(route('bookings.index'));
    }

    public function test_a_user_with_accounting_access_still_sees_the_dashboard(): void
    {
        $accountant = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $accountant->assignRole('Admin');

        $response = $this->actingAs($accountant)->get(route('dashboard'));

        $response->assertOk();
    }

    private function bookingAdmin(): User
    {
        $bookingAdmin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $bookingAdmin->assignRole('Booking Admin');

        return $bookingAdmin;
    }

    public function test_a_booking_admin_can_create_booking_types_and_bookable_items(): void
    {
        $bookingAdmin = $this->bookingAdmin();

        $this->actingAs($bookingAdmin)->post(route('booking-types.store'), ['name' => 'Baptism'])->assertSessionHasNoErrors();
        $type = BookingType::where('tenant_id', $this->tenant->id)->where('name', 'Baptism')->firstOrFail();

        $this->actingAs($bookingAdmin)->post(route('bookable-items.store'), [
            'name' => 'Baptistery',
            'account_id' => $this->revenue->id,
            'cost_center_id' => $this->costCenter->id,
            'booking_types' => [['booking_type_id' => $type->id, 'rate_type' => 'flat', 'price' => 300]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookable_items', ['tenant_id' => $this->tenant->id, 'name' => 'Baptistery']);
        $this->actingAs($bookingAdmin)->put(route('booking-types.update', $type), ['name' => 'Baptism service'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('booking_types', ['id' => $type->id, 'name' => 'Baptism service']);
    }

    public function test_a_booking_admin_can_still_make_bookings(): void
    {
        $item = $this->item(isExclusive: true);
        $this->offer($item, 'Holy Mass', 200);

        $this->actingAs($this->bookingAdmin())->get(route('bookings.index'))->assertOk();
    }

    public function test_a_booking_admin_cannot_take_payments_or_see_accounting(): void
    {
        $bookingAdmin = $this->bookingAdmin();

        $this->assertTrue($bookingAdmin->can('bookable_items.manage'));
        $this->assertFalse($bookingAdmin->can('bookings.manage'));
        $this->assertFalse($bookingAdmin->can('accounts.view'));

        $this->actingAs($bookingAdmin)->get(route('accounts.index'))->assertForbidden();
    }

    public function test_a_booking_admin_lands_on_the_booking_calendar_not_the_dashboard(): void
    {
        $this->actingAs($this->bookingAdmin())->get(route('dashboard'))->assertRedirect(route('bookings.index'));
    }

    public function test_a_booking_agent_can_view_bookable_items_but_not_manage_them(): void
    {
        $this->item(isExclusive: true);

        $viewResponse = $this->actingAs($this->user)->get(route('bookable-items.index'));
        $viewResponse->assertOk();

        $createResponse = $this->actingAs($this->user)->post(route('bookable-items.store'), [
            'name' => 'Should not be created',
            'rate_type' => 'hourly',
            'price' => 50,
            'account_id' => $this->revenue->id,
            'cost_center_id' => $this->costCenter->id,
        ]);
        $createResponse->assertForbidden();
    }
}
