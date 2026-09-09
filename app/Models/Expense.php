<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasCostCenter;
use App\Models\Concerns\HasMoney;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasCostCenter;
    use HasFactory;
    use HasMoney;

    protected $fillable = [
        'tenant_id',
        'description',
        'account_id',
        'amount',
        'date',
        'vendor_id',
        'cost_center_id',
        'payable_account_id',
        'receipt_path',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'status' => ExpenseStatus::class,
        'paid_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payable_account_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
