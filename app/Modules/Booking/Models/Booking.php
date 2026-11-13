<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Booking\Enums\BookingDiscountType;
use App\Modules\Booking\Enums\BookingPaymentMethod;
use App\Modules\Booking\Enums\BookingRecurrence;
use App\Modules\Booking\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'bookable_item_id',
        'booking_type_id',
        'user_id',
        'starts_at',
        'ends_at',
        'price',
        'discount_type',
        'discount_value',
        'discount_amount',
        'payment_method',
        'status',
        'notes',
        'recurrence',
        'recurrence_until',
        'recurrence_group_id',
        'paid_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'price' => 'decimal:2',
        'discount_type' => BookingDiscountType::class,
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'payment_method' => BookingPaymentMethod::class,
        'status' => BookingStatus::class,
        'recurrence' => BookingRecurrence::class,
        'recurrence_until' => 'date',
        'paid_at' => 'datetime',
    ];

    public function bookableItem(): BelongsTo
    {
        return $this->belongsTo(BookableItem::class);
    }

    public function bookingType(): BelongsTo
    {
        return $this->belongsTo(BookingType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * What is still owed: the full price until the booking is paid.
     */
    public function balanceDue(): float
    {
        return $this->status === BookingStatus::Paid ? 0.0 : round((float) $this->price, 2);
    }

    /**
     * Any non-cancelled booking for the given item whose time range
     * overlaps [$startsAt, $endsAt) — the conflict check for exclusive
     * items. $excludeId skips the booking being updated, if any.
     */
    public function scopeOverlapping(Builder $query, int $bookableItemId, string $startsAt, string $endsAt, ?int $excludeId = null): Builder
    {
        return $query->where('bookable_item_id', $bookableItemId)
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($excludeId, fn (Builder $query, int $excludeId) => $query->where('id', '!=', $excludeId));
    }
}
