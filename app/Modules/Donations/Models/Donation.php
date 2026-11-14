<?php

namespace App\Modules\Donations\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\CostCenter;
use App\Models\User;
use App\Modules\Donations\Enums\DonationPaymentMethod;
use App\Modules\Donations\Enums\DonationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    use Auditable;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'donor_id',
        'cost_center_id',
        'amount',
        'currency',
        'exchange_rate',
        'base_amount',
        'donated_at',
        'payment_method',
        'payment_reference',
        'status',
        'notes',
        'receipt_number',
        'created_by',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'base_amount' => 'decimal:2',
        'donated_at' => 'date',
        'payment_method' => DonationPaymentMethod::class,
        'status' => DonationStatus::class,
        'cancelled_at' => 'datetime',
    ];

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
