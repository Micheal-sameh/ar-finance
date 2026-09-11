<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasCostCenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasCostCenter;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'purchase_order_id',
        'bill_number',
        'bill_date',
        'due_date',
        'status',
        'payable_account_id',
        'tax_receivable_account_id',
        'cost_center_id',
        'paid_at',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'status' => BillStatus::class,
        'paid_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payable_account_id');
    }

    public function taxReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'tax_receivable_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class);
    }

    public function subtotal(): float
    {
        return round($this->lines->sum(fn (BillLine $line) => $line->subtotal()), 2);
    }

    public function totalTax(): float
    {
        return round($this->lines->sum(fn (BillLine $line) => $line->taxAmount()), 2);
    }

    public function total(): float
    {
        return round($this->subtotal() + $this->totalTax(), 2);
    }
}
