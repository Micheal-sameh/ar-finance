<?php

namespace App\Models;

use App\Enums\CostCenterType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasMoney;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCenter extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use HasMoney;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'budget',
        'parent_id',
        'is_active',
    ];

    protected $casts = [
        'type' => CostCenterType::class,
        'budget' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CostCenter::class, 'parent_id');
    }
}
