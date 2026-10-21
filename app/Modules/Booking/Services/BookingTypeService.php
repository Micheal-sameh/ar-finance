<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Exceptions\BookingTypeInUseException;
use App\Modules\Booking\Models\BookingType;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * The reusable catalog of booking types. Prices live on the bookable item a
 * type is attached to (see BookableItemService), not here.
 */
class BookingTypeService
{
    /**
     * @return Collection<int, BookingType>
     */
    public function allWithUsage(): Collection
    {
        return BookingType::query()
            ->withCount('bookableItems')
            ->with('tenant:id,name')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, BookingType>
     */
    public function options(): Collection
    {
        return BookingType::query()->orderBy('name')->get(['id', 'name']);
    }

    public function create(string $name): BookingType
    {
        return BookingType::create([
            'tenant_id' => app(TenantContext::class)->id(),
            'name' => $name,
        ]);
    }

    public function update(BookingType $type, string $name): BookingType
    {
        $type->update(['name' => $name]);

        return $type;
    }

    /**
     * @throws BookingTypeInUseException
     */
    public function delete(BookingType $type): void
    {
        if ($type->bookableItems()->exists() || $type->bookings()->exists()) {
            throw BookingTypeInUseException::inUse($type->name);
        }

        $type->delete();
    }
}
