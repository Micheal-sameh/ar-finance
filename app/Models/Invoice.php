<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'status',
        'currency',
        'exchange_rate',
        'revalued_exchange_rate',
        'receivable_account_id',
        'tax_payable_account_id',
        'paid_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'status' => InvoiceStatus::class,
        'exchange_rate' => 'decimal:6',
        'revalued_exchange_rate' => 'decimal:8',
        'paid_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }

    public function taxPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'tax_payable_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function subtotal(): float
    {
        return round($this->lines->sum(fn (InvoiceLine $line) => $line->subtotal()), 2);
    }

    public function totalTax(): float
    {
        return round($this->lines->sum(fn (InvoiceLine $line) => $line->taxAmount()), 2);
    }

    /**
     * Grand total, in the invoice's own currency — GL postings convert
     * this (and the subtotal/tax split) to the tenant's base currency
     * using exchange_rate. See InvoiceService::send().
     */
    public function total(): float
    {
        return round($this->subtotal() + $this->totalTax(), 2);
    }

    /**
     * The exchange rate the receivable is currently booked at. Starts as
     * exchange_rate (the rate at send() time) and moves to
     * revalued_exchange_rate once a currency revaluation or a payment
     * settlement re-measures it — both keep this in sync so neither one
     * ever re-derives a gain/loss the other already recognized.
     */
    public function bookedExchangeRate(): float
    {
        return (float) ($this->revalued_exchange_rate ?? $this->exchange_rate);
    }
}
