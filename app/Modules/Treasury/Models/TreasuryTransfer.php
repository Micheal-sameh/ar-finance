<?php

namespace App\Modules\Treasury\Models;

use App\Models\Account;
use App\Models\Concerns\Auditable;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Treasury\Enums\TreasuryTransferStatus;
use App\Modules\Treasury\Enums\TreasuryTransferType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of money through the treasury. Deliberately not tenant-scoped:
 * it belongs to two ledgers at once (the treasury's and the tenant's), so
 * the repository applies the visibility rules itself.
 */
class TreasuryTransfer extends Model
{
    use Auditable;

    protected $fillable = [
        'treasury_tenant_id',
        'tenant_id',
        'type',
        'status',
        'requested_amount',
        'accepted_amount',
        'transfer_date',
        'tenant_account_id',
        'treasury_account_id',
        'counter_account_id',
        'reference',
        'notes',
        'reject_reason',
        'requested_by',
        'decided_by',
        'decided_at',
        'voided_by',
        'voided_at',
    ];

    protected $casts = [
        'type' => TreasuryTransferType::class,
        'status' => TreasuryTransferStatus::class,
        'requested_amount' => 'decimal:2',
        'accepted_amount' => 'decimal:2',
        'transfer_date' => 'date',
        'decided_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    /**
     * What was actually posted (or, until accepted, what was asked for).
     */
    public function amount(): float
    {
        return (float) ($this->accepted_amount ?? $this->requested_amount);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function treasuryTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'treasury_tenant_id');
    }

    public function tenantAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'tenant_account_id')->withoutGlobalScopes();
    }

    public function treasuryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'treasury_account_id')->withoutGlobalScopes();
    }

    public function counterAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counter_account_id')->withoutGlobalScopes();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
