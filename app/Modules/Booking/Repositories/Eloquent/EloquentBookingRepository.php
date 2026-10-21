<?php

namespace App\Modules\Booking\Repositories\Eloquent;

use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentBookingRepository implements BookingRepositoryInterface
{
    public function find(int $id): ?Booking
    {
        return Booking::query()->with(['bookableItem', 'bookingType', 'user'])->find($id);
    }

    public function create(array $attributes): Booking
    {
        return Booking::create($attributes);
    }

    public function update(Booking $booking, array $attributes): Booking
    {
        $booking->update($attributes);

        return $booking;
    }

    public function hasConflict(int $bookableItemId, string $startsAt, string $endsAt, ?int $excludeId = null): bool
    {
        return Booking::query()->overlapping($bookableItemId, $startsAt, $endsAt, $excludeId)->exists();
    }

    public function forCalendar(string $from, string $to, ?int $bookableItemId = null): Collection
    {
        return Booking::query()
            ->with(['bookableItem', 'bookingType', 'user', 'tenant:id,name'])
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->when($bookableItemId, fn ($query, $id) => $query->where('bookable_item_id', $id))
            ->orderBy('starts_at')
            ->get();
    }

    public function awaitingRevenueRecognition(): Collection
    {
        return Booking::query()->with('bookableItem')->awaitingRevenueRecognition()->get();
    }
}
