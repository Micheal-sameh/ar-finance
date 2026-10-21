<?php

namespace App\Modules\Booking\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
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
        'deposit_amount',
        'balance_paid',
        'deposit_paid_at',
        'payment_due_at',
        'status',
        'notes',
        'recurrence',
        'recurrence_until',
        'recurrence_group_id',
        'paid_at',
        'revenue_recognized_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'price' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'balance_paid' => 'decimal:2',
        'deposit_paid_at' => 'datetime',
        'payment_due_at' => 'datetime',
        'status' => BookingStatus::class,
        'recurrence' => BookingRecurrence::class,
        'recurrence_until' => 'date',
        'paid_at' => 'datetime',
        'revenue_recognized_at' => 'datetime',
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
     * Past its payment deadline, with the balance still outstanding.
     * Purely informational — nothing cancels the booking automatically.
     */
    public function isOverdue(): bool
    {
        return $this->payment_due_at !== null
            && $this->payment_due_at->isPast()
            && $this->status === BookingStatus::Confirmed;
    }

    public function balanceDue(): float
    {
        return round((float) $this->price - (float) ($this->deposit_amount ?? 0) - (float) ($this->balance_paid ?? 0), 2);
    }

    /**
     * Paid, with the event over, but still sitting in deferred revenue —
     * what RecognizeBookingRevenue clears out.
     */
    public function scopeAwaitingRevenueRecognition(Builder $query): Builder
    {
        return $query->where('status', BookingStatus::Paid->value)
            ->whereNull('revenue_recognized_at')
            ->where('ends_at', '<=', now());
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
