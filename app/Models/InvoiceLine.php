<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'account_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function subtotal(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }

    /**
     * Line total including tax. Tax is currently folded into the same
     * revenue-account credit rather than split out to a VAT-payable
     * control account — see InvoiceService::send(). Revisit once the
     * Tax/VAT phase adds proper tax-account configuration.
     */
    public function lineTotal(): float
    {
        return round($this->subtotal() * (1 + (float) $this->tax_rate / 100), 2);
    }
}
