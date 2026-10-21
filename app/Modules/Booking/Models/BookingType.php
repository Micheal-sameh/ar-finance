<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable kind of booking — e.g. "Wedding Pray", "Holy Mass", "Meeting".
 * Defined once, then attached to any bookable item with a price for it
 * (see BookableItem::bookingTypes()).
 */
class BookingType extends Model
{
    use Auditable;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
    ];

    public function bookableItems(): BelongsToMany
    {
        return $this->belongsToMany(BookableItem::class, 'bookable_item_booking_type')->withPivot('price', 'rate_type');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
