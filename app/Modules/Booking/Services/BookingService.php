<?php

namespace App\Modules\Booking\Services;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\JournalSourceType;
use App\Modules\Booking\DTOs\CreateBookingData;
use App\Modules\Booking\Enums\BookingDiscountType;
use App\Modules\Booking\Enums\BookingPaymentMethod;
use App\Modules\Booking\Enums\BookingRateType;
use App\Modules\Booking\Enums\BookingRecurrence;
use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\Booking\Exceptions\BookingConflictException;
use App\Modules\Booking\Models\BookableItem;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Models\BookingType;
use App\Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\GlMappingService;
use App\Services\JournalService;
use App\Support\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Owns the booking lifecycle: pricing a slot from its item's rate,
 * enforcing the exclusive/shared conflict rule, and posting to the
 * ledger once a booking is paid.
 */
class BookingService
{
    /**
     * Safety cap so a daily booking with a far-off end date cannot create
     * thousands of rows in one request.
     */
    private const MAX_OCCURRENCES = 366;

    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly JournalService $journals,
        private readonly GlMappingService $glMapping,
    ) {}

    public function find(int $id): ?Booking
    {
        return $this->bookings->find($id);
    }

    /**
     * Books the slot — and, for a repeating booking, every repeat of it up to
     * the end date. Each occurrence is an ordinary booking of its own (own
     * conflict check and price), created all-or-nothing:
     * if any date is taken or fails, none are booked.
     *
     * @return Booking the first booking of the series
     *
     * @throws BookingConflictException
     */
    public function create(CreateBookingData $data, BookableItem $item, int $userId): Booking
    {
        $type = $data->bookingTypeId !== null
            ? $item->bookingTypes()->find($data->bookingTypeId)
            : null;

        if ($data->bookingTypeId !== null && $type === null) {
            throw new RuntimeException("That booking type is not offered for \"{$item->name}\".");
        }

        $occurrences = $this->occurrences($data);

        if ($item->is_exclusive) {
            foreach ($occurrences as [$startsAt, $endsAt]) {
                if ($this->bookings->hasConflict($item->id, $startsAt, $endsAt)) {
                    throw BookingConflictException::slotTaken(count($occurrences) > 1 ? "{$item->name} on {$startsAt}" : $item->name);
                }
            }
        }

        $groupId = count($occurrences) > 1 ? (string) Str::uuid() : null;

        return DB::transaction(function () use ($data, $item, $type, $userId, $occurrences, $groupId) {
            $first = null;

            foreach ($occurrences as [$startsAt, $endsAt]) {
                $booking = $this->createOccurrence($data, $item, $type, $userId, $startsAt, $endsAt, $groupId);
                $first ??= $booking;
            }

            return $first;
        });
    }

    /**
     * How many bookings were created together with this one — 1 for a
     * booking that doesn't repeat.
     */
    public function seriesSize(Booking $booking): int
    {
        return $booking->recurrence_group_id === null
            ? 1
            : $this->bookings->countInSeries($booking->recurrence_group_id);
    }

    /**
     * Start/end pairs for the booking and each repeat up to the end date.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function occurrences(CreateBookingData $data): array
    {
        if ($data->recurrence === BookingRecurrence::None || $data->recurrenceUntil === null) {
            return [[$data->startsAt, $data->endsAt]];
        }

        $start = CarbonImmutable::parse($data->startsAt);
        $end = CarbonImmutable::parse($data->endsAt);
        $until = CarbonImmutable::parse($data->recurrenceUntil)->endOfDay();
        $occurrences = [];

        for ($i = 0; ; $i++) {
            $occurrenceStart = $data->recurrence->shift($start, $i);

            if ($occurrenceStart->greaterThan($until)) {
                break;
            }

            if ($i >= self::MAX_OCCURRENCES) {
                throw new RuntimeException('This would create more than '.self::MAX_OCCURRENCES.' bookings — choose an earlier end date.');
            }

            $occurrences[] = [
                $occurrenceStart->toDateTimeString(),
                $occurrenceStart->add($start->diff($end))->toDateTimeString(),
            ];
        }

        return $occurrences;
    }

    private function createOccurrence(CreateBookingData $data, BookableItem $item, ?BookingType $type, int $userId, string $startsAt, string $endsAt, ?string $groupId): Booking
    {
        $item->revenueAccountIdFor($type?->id);

        $grossPrice = $this->priceFor($item, $type, $startsAt, $endsAt);
        $discountAmount = $this->discountFor($data, $grossPrice);

        $booking = $this->bookings->create([
            'tenant_id' => app(TenantContext::class)->id(),
            'bookable_item_id' => $item->id,
            'booking_type_id' => $type?->id,
            'user_id' => $userId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'price' => round($grossPrice - $discountAmount, 2),
            'discount_type' => $data->discountType,
            'discount_value' => $data->discountType ? $data->discountValue : null,
            'discount_amount' => $discountAmount,
            'status' => BookingStatus::Confirmed,
            'notes' => $data->notes,
            'payment_method' => $data->paymentMethod,
            'recurrence' => $groupId !== null ? $data->recurrence : BookingRecurrence::None,
            'recurrence_until' => $groupId !== null ? $data->recurrenceUntil : null,
            'recurrence_group_id' => $groupId,
        ]);

        return $this->settle($booking, $item, $data->paymentMethod);
    }

    /**
     * The discount in money for one booking priced at $grossPrice — a percent
     * of it, or a fixed amount (applied to each booking of a repeating
     * series). It can never take the price below zero.
     */
    private function discountFor(CreateBookingData $data, float $grossPrice): float
    {
        if ($data->discountType === null || $data->discountValue === null) {
            return 0.0;
        }

        $discount = $data->discountType === BookingDiscountType::Percent
            ? round($grossPrice * $data->discountValue / 100, 2)
            : round($data->discountValue, 2);

        if ($discount > $grossPrice) {
            throw new RuntimeException("The discount ({$discount}) can't be more than the booking's price ({$grossPrice}).");
        }

        return $discount;
    }

    /**
     * Each booking type is priced on its own per item, with its own rate
     * type (per hour or flat). The item's own price/rate only matter for
     * older items that predate booking types and have none attached.
     */
    private function priceFor(BookableItem $item, ?BookingType $type, string $startsAt, string $endsAt): float
    {
        $rate = (float) ($type?->pivot->price ?? $item->price);
        $rateType = $type ? BookingRateType::from($type->pivot->rate_type) : ($item->rate_type ?? BookingRateType::Flat);

        if ($rateType === BookingRateType::Flat) {
            return $rate;
        }

        $hours = Carbon::parse($startsAt)->diffInMinutes(Carbon::parse($endsAt)) / 60;

        return round($rate * $hours, 2);
    }

    /**
     * Cancels the booking. If it was already paid, each journal entry posted
     * for it is reversed as its own entry (debits and credits swapped, same
     * accounts and cost centers), so the money and revenue come back out of
     * the books exactly where they went in — all in one transaction.
     */
    /**
     * Cancels a booking. Bookings are paid when they are made, so what was
     * collected is returned: each ledger entry posted for it is reversed as
     * its own entry, crediting the account it was originally debited to.
     */
    public function cancel(Booking $booking): Booking
    {
        if ($booking->status === BookingStatus::Cancelled) {
            throw new RuntimeException('This booking is already cancelled.');
        }

        return DB::transaction(function () use ($booking) {
            foreach ($this->bookings->journalEntries($booking) as $entry) {
                $this->journals->postJournalEntry(new CreateJournalEntryData(
                    date: now()->toDateString(),
                    description: "Cancelled Booking #{$booking->id} — reversal of: {$entry->description}",
                    reference: null,
                    sourceType: JournalSourceType::Booking,
                    sourceId: $booking->id,
                    createdBy: auth()->id(),
                    lines: $entry->lines->map(fn ($line) => new JournalLineData(
                        accountId: $line->account_id,
                        debit: (float) $line->credit,
                        credit: (float) $line->debit,
                        costCenterId: $line->cost_center_id,
                    ))->all(),
                ));
            }

            return $this->bookings->update($booking, ['status' => BookingStatus::Cancelled]);
        });
    }

    /**
     * Bookings are paid the moment they are made: Dr the account mapped to the
     * chosen payment method (cash or bank transfer) / Cr the revenue account for the booking's type,
     * both at the type's cost center. A free booking owes nothing, so it is
     * marked paid without touching the ledger.
     */
    private function settle(Booking $booking, BookableItem $item, BookingPaymentMethod $method): Booking
    {
        $price = (float) $booking->price;

        if ($price > 0) {
            $role = $method->glRole();
            $paymentAccountId = $this->glMapping->defaults()[$role->value] ?? null;

            if ($paymentAccountId === null) {
                throw new RuntimeException("No account is set for {$method->label()} payments — ask finance or an admin to set \"{$role->label()}\" in the General Ledger mapping, or choose another payment method.");
            }

            $costCenterId = $item->costCenterIdFor($booking->booking_type_id);

            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: now()->toDateString(),
                description: "Booking #{$booking->id} — {$item->name}",
                reference: null,
                sourceType: JournalSourceType::Booking,
                sourceId: $booking->id,
                createdBy: auth()->id(),
                lines: [
                    new JournalLineData(accountId: $paymentAccountId, debit: $price, credit: 0, costCenterId: $costCenterId),
                    new JournalLineData(accountId: $item->revenueAccountIdFor($booking->booking_type_id), debit: 0, credit: $price, costCenterId: $costCenterId),
                ],
            ));
        }

        return $this->bookings->update($booking, ['status' => BookingStatus::Paid, 'paid_at' => now()]);
    }

    private function reverseJournalEntries(Booking $booking): void
    {
        foreach ($this->bookings->journalEntries($booking) as $entry) {
            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: now()->toDateString(),
                description: "Cancelled Booking #{$booking->id} — reversal of: {$entry->description}",
                reference: null,
                sourceType: JournalSourceType::Booking,
                sourceId: $booking->id,
                createdBy: auth()->id(),
                lines: $entry->lines->map(fn ($line) => new JournalLineData(
                    accountId: $line->account_id,
                    debit: (float) $line->credit,
                    credit: (float) $line->debit,
                    costCenterId: $line->cost_center_id,
                ))->all(),
            ));
        }
    }

    /**
     * @return Collection<int, Booking>
     */
    public function calendar(string $from, string $to, ?int $bookableItemId = null): Collection
    {
        return $this->bookings->forCalendar($from, $to, $bookableItemId);
    }
}
