<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\CostCenter;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Booking\Models\BookableItem;
use App\Modules\Booking\Models\BookingType;
use App\Modules\Booking\Services\BookingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Account $revenue;

    private Account $bank;

    private Account $deferredRevenue;

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

        $this->deferredRevenue = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '2100',
            'name' => 'Deferred Revenue',
            'type' => AccountType::Liability,
            'normal_balance' => 'credit',
        ]);

        $this->costCenter = CostCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Front Desk',
            'type' => 'profit',
        ]);
    }

    private function item(bool $isExclusive, ?float $depositPercent = null): BookableItem
    {
        return BookableItem::create([
            'tenant_id' => $this->tenant->id,
            'name' => $isExclusive ? 'Treatment Room' : 'Group Class',
            'is_exclusive' => $isExclusive,
            'rate_type' => 'hourly',
            'price' => 100,
            'deposit_percent' => $depositPercent,
            'account_id' => $this->revenue->id,
            'deferred_revenue_account_id' => $depositPercent ? $this->deferredRevenue->id : null,
            'deposit_payment_account_id' => $depositPercent ? $this->bank->id : null,
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
            'booking_type_id' => $wedding->id,
            'starts_at' => '2027-01-10 10:00:00',
            'ends_at' => '2027-01-10 14:00:00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['booking_type_id' => $wedding->id, 'price' => 500]);
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
            'starts_at' => '2027-01-04 10:00:00',
            'ends_at' => '2027-01-04 12:00:00',
            'recurrence' => 'weekly',
            'recurrence_until' => '2027-01-25',
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Booked 4 times.');

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
            'starts_at' => '2027-01-11 10:00:00',
            'ends_at' => '2027-01-11 12:00:00',
        ]);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
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
            'starts_at' => '2027-01-04 10:00:00',
            'ends_at' => '2027-01-04 12:00:00',
            'recurrence' => 'daily',
        ])->assertSessionHasErrors('recurrence_until');

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2027-01-04 10:00:00',
            'ends_at' => '2027-01-04 12:00:00',
            'recurrence' => 'none',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $item->bookings()->count());
    }

    public function test_calendar_feed_returns_a_plain_array_not_a_data_envelope(): void
    {
        // This app disables JsonResource's default {"data": [...]} wrapping
        // app-wide (see JsonResource::withoutWrapping() in AppServiceProvider),
        // so the frontend reads the response body directly as the array.
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
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
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'bookable_item_id' => $item->id,
            'price' => 200.00,
            'status' => 'confirmed',
        ]);
    }

    public function test_an_exclusive_item_cannot_be_double_booked_for_an_overlapping_slot(): void
    {
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ]);

        $second = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $second->assignRole('Booking Agent');

        $response = $this->actingAs($second)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
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
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 11:00:00',
        ]);

        $second = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $second->assignRole('Booking Agent');

        $response = $this->actingAs($second)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 11:00:00',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_marking_a_booking_paid_posts_revenue_against_the_items_account_and_center(): void
    {
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ]);

        $booking = $item->bookings()->firstOrFail();

        $manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $manager->assignRole('Admin');

        $response = $this->actingAs($manager)->post(route('bookings.mark-paid', $booking), [
            'payment_account_id' => $this->bank->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'paid']);
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

    public function test_a_booking_agent_cannot_mark_a_booking_paid(): void
    {
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ]);

        $booking = $item->bookings()->firstOrFail();

        $response = $this->actingAs($this->user)->post(route('bookings.mark-paid', $booking), [
            'payment_account_id' => $this->bank->id,
        ]);

        $response->assertForbidden();
    }

    public function test_booking_an_item_with_a_deposit_percent_collects_the_deposit_immediately(): void
    {
        $item = $this->item(isExclusive: true, depositPercent: 30);

        $response = $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
            'payment_due_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $booking = $item->bookings()->firstOrFail();

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
            'deposit_amount' => 60.00,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'account_id' => $this->bank->id,
            'debit' => 60.00,
            'cost_center_id' => $this->costCenter->id,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'account_id' => $this->deferredRevenue->id,
            'credit' => 60.00,
            'cost_center_id' => $this->costCenter->id,
        ]);
    }

    public function test_booking_can_pay_more_than_the_minimum_deposit(): void
    {
        $item = $this->item(isExclusive: true, depositPercent: 30);

        $response = $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
            'payment_due_at' => now()->addDays(3)->toDateTimeString(),
            'deposit_amount' => 150,
        ]);

        $booking = $item->bookings()->firstOrFail();

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'deposit_amount' => 150.00]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bank->id, 'debit' => 150.00]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->deferredRevenue->id, 'credit' => 150.00]);
    }

    public function test_booking_rejects_a_deposit_below_the_items_minimum(): void
    {
        $item = $this->item(isExclusive: true, depositPercent: 30);

        $response = $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
            'payment_due_at' => now()->addDays(3)->toDateTimeString(),
            'deposit_amount' => 10,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_rejects_a_deposit_above_the_price(): void
    {
        $item = $this->item(isExclusive: true, depositPercent: 30);

        $response = $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
            'payment_due_at' => now()->addDays(3)->toDateTimeString(),
            'deposit_amount' => 500,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_marking_paid_after_a_deposit_clears_deferred_revenue_and_collects_only_the_balance(): void
    {
        $item = $this->item(isExclusive: true, depositPercent: 30);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
            'payment_due_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $booking = $item->bookings()->firstOrFail();

        $manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $manager->assignRole('Admin');

        $response = $this->actingAs($manager)->post(route('bookings.mark-paid', $booking), [
            'payment_account_id' => $this->bank->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'paid', 'revenue_recognized_at' => null]);
        // Remaining balance (200 - 60 = 140) collected in cash, but it's
        // still 2026-10-10's event hasn't happened yet by test time, so it
        // stays deferred rather than landing in revenue straight away.
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bank->id, 'debit' => 140.00]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->deferredRevenue->id, 'credit' => 140.00]);
        $this->assertDatabaseMissing('journal_lines', ['account_id' => $this->revenue->id]);
    }

    public function test_marking_paid_after_the_event_has_already_ended_recognizes_revenue_immediately(): void
    {
        $item = $this->item(isExclusive: true, depositPercent: 30);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => now()->subHours(3)->toDateTimeString(),
            'ends_at' => now()->subHour()->toDateTimeString(),
            'payment_due_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $booking = $item->bookings()->firstOrFail();

        $manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $manager->assignRole('Admin');

        $response = $this->actingAs($manager)->post(route('bookings.mark-paid', $booking), [
            'payment_account_id' => $this->bank->id,
        ]);

        $response->assertRedirect();
        $this->assertNotNull($booking->fresh()->revenue_recognized_at);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->deferredRevenue->id, 'debit' => 200.00]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->revenue->id, 'credit' => 200.00]);
    }

    public function test_recognizing_revenue_for_ended_bookings_clears_deferred_revenue_for_a_booking_paid_ahead_of_its_event(): void
    {
        $item = $this->item(isExclusive: true, depositPercent: 30);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
            'payment_due_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $booking = $item->bookings()->firstOrFail();

        $manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $manager->assignRole('Admin');

        $this->actingAs($manager)->post(route('bookings.mark-paid', $booking), [
            'payment_account_id' => $this->bank->id,
        ]);

        $this->assertNull($booking->fresh()->revenue_recognized_at);

        // The event is now in the past — recognition should pick it up.
        $booking->update(['ends_at' => now()->subHour()]);

        app(BookingService::class)->recognizeRevenueForEndedBookings();

        $this->assertNotNull($booking->fresh()->revenue_recognized_at);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->deferredRevenue->id, 'debit' => 200.00]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->revenue->id, 'credit' => 200.00]);
    }

    public function test_collecting_a_partial_balance_payment_posts_to_deferred_revenue_and_leaves_the_booking_confirmed(): void
    {
        $item = $this->item(isExclusive: true, depositPercent: 30);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
            'payment_due_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $booking = $item->bookings()->firstOrFail();

        $manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $manager->assignRole('Admin');

        // Balance due is 140 (200 price - 60 deposit); collect only 50 of it.
        $response = $this->actingAs($manager)->post(route('bookings.mark-paid', $booking), [
            'payment_account_id' => $this->bank->id,
            'amount' => 50,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed', 'balance_paid' => 50.00]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bank->id, 'debit' => 50.00]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->deferredRevenue->id, 'credit' => 50.00]);
        // Not fully settled yet, so nothing should have hit revenue.
        $this->assertDatabaseMissing('journal_lines', ['account_id' => $this->revenue->id]);
    }

    public function test_collecting_the_remaining_balance_after_a_partial_payment_settles_the_booking(): void
    {
        $item = $this->item(isExclusive: true, depositPercent: 30);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
            'payment_due_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $booking = $item->bookings()->firstOrFail();

        $manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $manager->assignRole('Admin');

        $this->actingAs($manager)->post(route('bookings.mark-paid', $booking), [
            'payment_account_id' => $this->bank->id,
            'amount' => 50,
        ]);

        // 200 price - 60 deposit - 50 partial = 90 left.
        $response = $this->actingAs($manager)->post(route('bookings.mark-paid', $booking), [
            'payment_account_id' => $this->bank->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'paid', 'balance_paid' => 140.00, 'revenue_recognized_at' => null]);
        // This final slice (90) is collected the same way the deposit and
        // the earlier partial payment were — into deferred revenue, since
        // the event (2026-10-10) hasn't happened by test time yet.
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->bank->id, 'debit' => 90.00]);
        $this->assertDatabaseHas('journal_lines', ['account_id' => $this->deferredRevenue->id, 'credit' => 90.00]);
        $this->assertDatabaseMissing('journal_lines', ['account_id' => $this->revenue->id]);
    }

    public function test_collecting_more_than_the_remaining_balance_is_rejected(): void
    {
        $item = $this->item(isExclusive: true, depositPercent: 30);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
            'payment_due_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $booking = $item->bookings()->firstOrFail();

        $manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $manager->assignRole('Admin');

        $response = $this->actingAs($manager)->post(route('bookings.mark-paid', $booking), [
            'payment_account_id' => $this->bank->id,
            'amount' => 141,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed', 'balance_paid' => 0]);
    }

    public function test_a_booking_with_a_paid_deposit_cannot_be_cancelled(): void
    {
        $item = $this->item(isExclusive: true, depositPercent: 30);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
            'payment_due_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $booking = $item->bookings()->firstOrFail();

        $response = $this->actingAs($this->user)->post(route('bookings.cancel', $booking));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
    }

    public function test_a_booking_past_its_payment_deadline_is_flagged_overdue(): void
    {
        $item = $this->item(isExclusive: true, depositPercent: 30);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
            'payment_due_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $booking = $item->bookings()->firstOrFail();
        $booking->update(['deposit_amount' => 60, 'deposit_paid_at' => now(), 'payment_due_at' => now()->subDay()]);

        $this->assertTrue($booking->fresh()->isOverdue());
    }

    public function test_an_item_without_a_deposit_percent_rejects_deposit_payment(): void
    {
        $item = $this->item(isExclusive: true);

        $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ]);

        $booking = $item->bookings()->firstOrFail();

        $manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $manager->assignRole('Admin');

        $response = $this->actingAs($manager)->post(route('bookings.pay-deposit', $booking), [
            'payment_account_id' => $this->bank->id,
            'payment_due_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'deposit_amount' => null]);
    }

    public function test_booking_an_item_with_a_deposit_percent_requires_the_deposit_payment_fields(): void
    {
        $item = $this->item(isExclusive: true, depositPercent: 30);

        $response = $this->actingAs($this->user)->post(route('bookings.store'), [
            'bookable_item_id' => $item->id,
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 12:00:00',
        ]);

        $response->assertSessionHasErrors(['payment_due_at']);
        $this->assertDatabaseCount('bookings', 0);
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
