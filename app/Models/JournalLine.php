<?php

namespace App\Models;

use App\Models\Concerns\HasCostCenter;
use App\Models\Concerns\HasMoney;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use HasCostCenter;
    use HasFactory;
    use HasMoney;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
        'cost_center_id',
        'description',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
