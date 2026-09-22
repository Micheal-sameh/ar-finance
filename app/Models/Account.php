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
        'currency',
        'parent_id',
        'is_active',
        'is_deletable',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'normal_balance' => NormalBalance::class,
        'is_active' => 'boolean',
        'is_deletable' => 'boolean',
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

    /**
     * The chart-of-accounts numbering convention nests a child under its
     * parent by taking over exactly the parent's next trailing zero: 1000
     * (Assets) -> 1100 -> 1110 -> 1111. A direct child must be the same
     * length as its parent, match it up to the parent's first trailing
     * zero, vary that one digit, and leave every digit after it zero — so
     * 1110 is a valid child of 1100, but 1101 and 1120 are not (1101
     * belongs under 1110 or a sibling of it; 1120 is a sibling of 1110,
     * not a child of it).
     */
    public static function significantCodePrefix(string $code): string
    {
        return rtrim($code, '0') ?: $code;
    }

    public static function codeNestsUnder(string $childCode, string $parentCode): bool
    {
        if ($childCode === $parentCode || strlen($childCode) !== strlen($parentCode)) {
            return false;
        }

        $position = strlen(self::significantCodePrefix($parentCode));

        if ($position >= strlen($parentCode)) {
            return false;
        }

        if (substr($childCode, 0, $position) !== substr($parentCode, 0, $position)) {
            return false;
        }

        $suffix = substr($childCode, $position + 1);

        return $suffix === str_repeat('0', strlen($suffix));
    }
}
