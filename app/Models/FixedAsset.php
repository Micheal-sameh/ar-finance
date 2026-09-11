<?php

namespace App\Models;

use App\Enums\DepreciationMethod;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAsset extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'purchase_date',
        'cost',
        'salvage_value',
        'useful_life_years',
        'depreciation_method',
        'asset_account_id',
        'depreciation_account_id',
        'accumulated_depreciation_account_id',
        'accumulated_depreciation',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'depreciation_method' => DepreciationMethod::class,
        'accumulated_depreciation' => 'decimal:2',
    ];

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function depreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    public function depreciableBase(): float
    {
        return round((float) $this->cost - (float) $this->salvage_value, 2);
    }

    public function monthlyDepreciation(): float
    {
        return $this->depreciation_method->monthlyAmount(
            (float) $this->cost,
            (float) $this->salvage_value,
            $this->useful_life_years,
        );
    }

    public function remainingDepreciable(): float
    {
        return max(0.0, round($this->depreciableBase() - (float) $this->accumulated_depreciation, 2));
    }

    public function isFullyDepreciated(): bool
    {
        return $this->remainingDepreciable() < 0.005;
    }

    public function netBookValue(): float
    {
        return round((float) $this->cost - (float) $this->accumulated_depreciation, 2);
    }
}
