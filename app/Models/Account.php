<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'normal_balance',
        'parent_id',
        'is_active',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'normal_balance' => NormalBalance::class,
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
