<?php

namespace App\Modules\Booking\Models;

use App\Models\Account;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasCostCenter;
use App\Modules\Booking\Enums\BookingRateType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class BookableItem extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasCostCenter;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_exclusive',
        'rate_type',
        'price',
        'account_id',
        'cost_center_id',
        'is_active',
    ];

    protected $casts = [
        'is_exclusive' => 'boolean',
        'rate_type' => BookingRateType::class,
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The revenue account a booking of the given type credits: the type's
     * own account on this item, else the item's default revenue account.
     */
    public function revenueAccountIdFor(?int $bookingTypeId): int
    {
        $typeAccountId = $bookingTypeId === null
            ? null
            : $this->bookingTypes->firstWhere('id', $bookingTypeId)?->pivot->account_id;

        $accountId = $typeAccountId ?? $this->account_id;

        if ($accountId === null) {
            throw new RuntimeException("\"{$this->name}\" has no revenue account yet — ask finance or an admin to set up its accounting.");
        }

        return $accountId;
    }

    /**
     * The cost/profit center a booking of the given type posts to: the
     * type's own center on this item, else the item's default center.
     */
    public function costCenterIdFor(?int $bookingTypeId): ?int
    {
        $typeCostCenterId = $bookingTypeId === null
            ? null
            : $this->bookingTypes->firstWhere('id', $bookingTypeId)?->pivot->cost_center_id;

        return $typeCostCenterId ?? $this->cost_center_id;
    }

    /**
     * The booking types offered on this item; $type->pivot->price is what
     * that type costs here and $type->pivot->account_id its own revenue
     * account, if any.
     */
    public function bookingTypes(): BelongsToMany
    {
        return $this->belongsToMany(BookingType::class, 'bookable_item_booking_type')
            ->withPivot('price', 'rate_type', 'account_id', 'cost_center_id')
            ->withTimestamps()
            ->orderBy('booking_types.name');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
