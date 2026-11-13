<?php

namespace App\Modules\Booking\Repositories\Contracts;

use App\Modules\Booking\Models\Booking;
use Illuminate\Database\Eloquent\Collection;

interface BookingRepositoryInterface
{
    public function find(int $id): ?Booking;

    public function create(array $attributes): Booking;

    public function update(Booking $booking, array $attributes): Booking;

    /**
     * True if a non-cancelled booking already occupies this item's slot.
     */
    public function hasConflict(int $bookableItemId, string $startsAt, string $endsAt, ?int $excludeId = null): bool;

    /**
     * Non-cancelled bookings whose range intersects [$from, $to), for the
     * calendar feed. Optionally scoped to one bookable item.
     */
    public function forCalendar(string $from, string $to, ?int $bookableItemId = null): Collection;

    public function countInSeries(string $recurrenceGroupId): int;

    /**
     * Every journal entry posted for the booking, with its lines.
     *
     * @return Collection<int, JournalEntry>
     */
    public function journalEntries(Booking $booking): Collection;
}
