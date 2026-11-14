<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra ledger account a non-cash payment method (bank transfer,
 * InstaPay, POS) can be paid into, on top of the one in the GL mapping.
 */
class PaymentAccount extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'method',
        'account_id',
        'label',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
