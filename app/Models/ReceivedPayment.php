<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money received that isn't settling an invoice — an advance, deposit or
 * one-off receipt. Posts Dr deposit account / Cr credit account.
 */
class ReceivedPayment extends Model
{
    use Auditable;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'description',
        'amount',
        'received_on',
        'deposit_account_id',
        'credit_account_id',
        'reference',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_on' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function depositAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deposit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
