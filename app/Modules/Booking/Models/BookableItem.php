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
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'deposit_percent',
        'account_id',
        'deferred_revenue_account_id',
        'deposit_payment_account_id',
        'cost_center_id',
        'is_active',
    ];

    protected $casts = [
        'is_exclusive' => 'boolean',
        'rate_type' => BookingRateType::class,
        'price' => 'decimal:2',
        'deposit_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function deferredRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deferred_revenue_account_id');
    }

    public function depositPaymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deposit_payment_account_id');
    }

    public function acceptsDeposits(): bool
    {
        return (float) ($this->deposit_percent ?? 0) > 0;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
