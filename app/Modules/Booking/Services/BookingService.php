<?php

namespace App\Modules\Booking\Services;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\JournalSourceType;
use App\Models\Tenant;
use App\Modules\Booking\DTOs\CreateBookingData;
use App\Modules\Booking\Enums\BookingRateType;
use App\Modules\Booking\Enums\BookingRecurrence;
use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\Booking\Exceptions\BookingConflictException;
use App\Modules\Booking\Models\BookableItem;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Models\BookingType;
use App\Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
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
    ) {}

    public function find(int $id): ?Booking
    {
        return $this->bookings->find($id);
    }

    /**
     * Books the slot — and, for a repeating booking, every repeat of it up to
     * the end date. Each occurrence is an ordinary booking of its own (own
     * conflict check, price, deposit and payments), created all-or-nothing:
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
        $booking = $this->bookings->create([
            'tenant_id' => app(TenantContext::class)->id(),
            'bookable_item_id' => $item->id,
            'booking_type_id' => $type?->id,
            'user_id' => $userId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'price' => $this->priceFor($item, $type, $startsAt, $endsAt),
            'status' => BookingStatus::Confirmed,
            'notes' => $data->notes,
            'recurrence' => $groupId !== null ? $data->recurrence : BookingRecurrence::None,
            'recurrence_until' => $groupId !== null ? $data->recurrenceUntil : null,
            'recurrence_group_id' => $groupId,
        ]);

        // The item's deposit percentage and payment account are set by the
        // admin; the request layer only requires a due date once the item
        // accepts deposits, so collect it immediately. For a repeat, the
        // balance deadline moves out with the occurrence.
        if ($item->acceptsDeposits() && $data->paymentDueAt !== null) {
            if ($item->deposit_payment_account_id === null) {
                throw new RuntimeException("\"{$item->name}\" has no deposit payment account configured — set one on the item before booking it.");
            }

            $dueAt = CarbonImmutable::parse($data->paymentDueAt)->add(CarbonImmutable::parse($data->startsAt)->diff(CarbonImmutable::parse($startsAt)));

            $booking = $this->payDeposit($booking, $item->deposit_payment_account_id, $dueAt->toDateTimeString(), $data->depositAmount);
        }

        return $booking;
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

    public function cancel(Booking $booking): Booking
    {
        if ($booking->status === BookingStatus::Paid) {
            throw new RuntimeException('A paid booking cannot be cancelled — it has already been posted to the ledger.');
        }

        if ($booking->status === BookingStatus::Cancelled) {
            throw new RuntimeException('This booking is already cancelled.');
        }

        if ($booking->deposit_paid_at !== null || (float) ($booking->balance_paid ?? 0) > 0) {
            throw new RuntimeException('Payment has been collected on this booking — refund it before cancelling.');
        }

        return $this->bookings->update($booking, ['status' => BookingStatus::Cancelled]);
    }

    /**
     * Posts Dr $paymentAccountId / Cr the item's deferred revenue account
     * (at its cost center) for the deposit, and records the deadline by
     * which the remaining balance is due. The deposit only moves to actual
     * revenue once the booking is fully paid — see markPaid().
     *
     * $amount lets the person paying offer more than the item's
     * deposit_percent minimum; it's capped at the booking's price so the
     * journal entry in markPaid() never goes negative.
     */
    public function payDeposit(Booking $booking, int $paymentAccountId, string $paymentDueAt, ?float $amount = null): Booking
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            throw new RuntimeException('Only a confirmed booking can take a deposit.');
        }

        if ($booking->deposit_paid_at !== null) {
            throw new RuntimeException('A deposit has already been paid for this booking.');
        }

        $item = $booking->bookableItem;

        if (! $item->acceptsDeposits()) {
            throw new RuntimeException("\"{$item->name}\" does not accept deposits.");
        }

        if ($item->deferred_revenue_account_id === null) {
            throw new RuntimeException("\"{$item->name}\" has no deferred revenue account configured.");
        }

        $minimumDeposit = round((float) $booking->price * ((float) $item->deposit_percent / 100), 2);
        $depositAmount = $amount ?? $minimumDeposit;

        if ($depositAmount < $minimumDeposit) {
            throw new RuntimeException("The deposit must be at least {$minimumDeposit}.");
        }

        if ($depositAmount > (float) $booking->price) {
            throw new RuntimeException("The deposit can't be more than the booking's price ({$booking->price}).");
        }

        return DB::transaction(function () use ($booking, $item, $paymentAccountId, $paymentDueAt, $depositAmount) {
            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: now()->toDateString(),
                description: "Deposit for Booking #{$booking->id} — {$item->name}",
                reference: null,
                sourceType: JournalSourceType::Booking,
                sourceId: $booking->id,
                createdBy: auth()->id(),
                lines: [
                    new JournalLineData(accountId: $paymentAccountId, debit: $depositAmount, credit: 0, costCenterId: $item->cost_center_id),
                    new JournalLineData(accountId: $item->deferred_revenue_account_id, debit: 0, credit: $depositAmount, costCenterId: $item->cost_center_id),
                ],
            ));

            return $this->bookings->update($booking, [
                'deposit_amount' => $depositAmount,
                'deposit_paid_at' => now(),
                'payment_due_at' => $paymentDueAt,
            ]);
        });
    }

    /**
     * Collects toward the remaining balance. $amount defaults to the full
     * remaining balance; a smaller amount records a partial payment and
     * leaves the booking confirmed with a smaller balance due.
     *
     * Either way the cash posts Dr $paymentAccountId / Cr the item's
     * deferred revenue account — same clearing account the deposit uses.
     * Collecting the last slice flips status to Paid, but the money stays
     * in deferred revenue: revenue isn't recognised until the event itself
     * has finished (see recognizeRevenue()/RecognizeBookingRevenue), unless
     * it already has by the time this final payment lands, in which case
     * it's recognised immediately instead of waiting on the scheduled job.
     *
     * Items with no deferred revenue account (never accept deposits, so
     * nothing can be partially collected) are settled the old way instead:
     * straight Dr $paymentAccountId / Cr revenue, recognised on the spot.
     */
    public function markPaid(Booking $booking, int $paymentAccountId, ?float $amount = null): Booking
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            throw new RuntimeException('Only a confirmed booking can be marked as paid.');
        }

        $item = $booking->bookableItem;
        $price = (float) $booking->price;
        $alreadyCollected = (float) ($booking->deposit_amount ?? 0) + (float) ($booking->balance_paid ?? 0);
        $remaining = round($price - $alreadyCollected, 2);

        if ($remaining <= 0) {
            throw new RuntimeException('This booking has already been paid in full.');
        }

        $collecting = $amount ?? $remaining;

        if ($collecting <= 0) {
            throw new RuntimeException('Enter a valid amount to collect.');
        }

        if ($collecting > $remaining) {
            throw new RuntimeException("Can't collect more than the remaining balance ({$remaining}).");
        }

        $isFinalPayment = $collecting >= $remaining;

        if (! $isFinalPayment && $item->deferred_revenue_account_id === null) {
            throw new RuntimeException("\"{$item->name}\" has no deferred revenue account configured — partial payments aren't supported until it's set, or collect the full balance instead.");
        }

        return DB::transaction(function () use ($booking, $item, $paymentAccountId, $price, $collecting, $isFinalPayment) {
            if ($item->deferred_revenue_account_id === null) {
                $this->journals->postJournalEntry(new CreateJournalEntryData(
                    date: now()->toDateString(),
                    description: "Booking #{$booking->id} — {$item->name}",
                    reference: null,
                    sourceType: JournalSourceType::Booking,
                    sourceId: $booking->id,
                    createdBy: auth()->id(),
                    lines: [
                        new JournalLineData(accountId: $paymentAccountId, debit: $collecting, credit: 0, costCenterId: $item->cost_center_id),
                        new JournalLineData(accountId: $item->account_id, debit: 0, credit: $price, costCenterId: $item->cost_center_id),
                    ],
                ));

                return $this->bookings->update($booking, [
                    'balance_paid' => (float) ($booking->balance_paid ?? 0) + $collecting,
                    'status' => BookingStatus::Paid,
                    'paid_at' => now(),
                    'revenue_recognized_at' => now(),
                ]);
            }

            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: now()->toDateString(),
                description: ($isFinalPayment ? "Final payment for Booking #{$booking->id}" : "Partial payment for Booking #{$booking->id}")." — {$item->name}",
                reference: null,
                sourceType: JournalSourceType::Booking,
                sourceId: $booking->id,
                createdBy: auth()->id(),
                lines: [
                    new JournalLineData(accountId: $paymentAccountId, debit: $collecting, credit: 0, costCenterId: $item->cost_center_id),
                    new JournalLineData(accountId: $item->deferred_revenue_account_id, debit: 0, credit: $collecting, costCenterId: $item->cost_center_id),
                ],
            ));

            $booking = $this->bookings->update($booking, [
                'balance_paid' => (float) ($booking->balance_paid ?? 0) + $collecting,
                ...($isFinalPayment ? ['status' => BookingStatus::Paid, 'paid_at' => now()] : []),
            ]);

            if ($isFinalPayment && $booking->ends_at->isPast()) {
                $booking = $this->recognizeRevenue($booking);
            }

            return $booking;
        });
    }

    /**
     * Clears everything collected for a fully-paid booking out of deferred
     * revenue into the item's actual revenue account, once the event it
     * was for has finished. Called directly from markPaid() when the event
     * has already ended by settlement time, and by RecognizeBookingRevenue
     * for bookings that were paid ahead of their event.
     */
    public function recognizeRevenue(Booking $booking): Booking
    {
        if ($booking->status !== BookingStatus::Paid) {
            throw new RuntimeException('Only a paid booking can have its revenue recognized.');
        }

        if ($booking->revenue_recognized_at !== null) {
            throw new RuntimeException('Revenue has already been recognized for this booking.');
        }

        $item = $booking->bookableItem;
        $collected = round((float) ($booking->deposit_amount ?? 0) + (float) ($booking->balance_paid ?? 0), 2);

        return DB::transaction(function () use ($booking, $item, $collected) {
            if ($collected > 0 && $item->deferred_revenue_account_id !== null) {
                $this->journals->postJournalEntry(new CreateJournalEntryData(
                    date: now()->toDateString(),
                    description: "Revenue recognized for Booking #{$booking->id} — {$item->name}",
                    reference: null,
                    sourceType: JournalSourceType::Booking,
                    sourceId: $booking->id,
                    createdBy: auth()->id(),
                    lines: [
                        new JournalLineData(accountId: $item->deferred_revenue_account_id, debit: $collected, credit: 0, costCenterId: $item->cost_center_id),
                        new JournalLineData(accountId: $item->account_id, debit: 0, credit: $collected, costCenterId: $item->cost_center_id),
                    ],
                ));
            }

            return $this->bookings->update($booking, ['revenue_recognized_at' => now()]);
        });
    }

    /**
     * @return Collection<int, Booking>
     */
    public function calendar(string $from, string $to, ?int $bookableItemId = null): Collection
    {
        return $this->bookings->forCalendar($from, $to, $bookableItemId);
    }

    /**
     * Runs recognizeRevenue() for every tenant's paid-but-not-yet-
     * recognized bookings whose event has finished — what
     * RecognizeBookingRevenue calls on its schedule. Loops tenants
     * explicitly (console runs have no active tenant of their own) so it
     * covers everyone in one pass.
     *
     * @return array<int, array{booking: Booking, recognized: bool, message: ?string}>
     */
    public function recognizeRevenueForEndedBookings(): array
    {
        $results = [];
        $tenantContext = app(TenantContext::class);

        foreach (Tenant::all() as $tenant) {
            $tenantContext->runAs($tenant->id, function () use (&$results) {
                foreach ($this->bookings->awaitingRevenueRecognition() as $booking) {
                    try {
                        $this->recognizeRevenue($booking);
                        $results[] = ['booking' => $booking, 'recognized' => true, 'message' => null];
                    } catch (RuntimeException $e) {
                        $results[] = ['booking' => $booking, 'recognized' => false, 'message' => $e->getMessage()];
                    }
                }
            });
        }

        return $results;
    }
}
