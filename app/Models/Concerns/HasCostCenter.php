<?php

namespace App\Models\Concerns;

use App\Models\CostCenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mixed into any model that can be tagged with a nullable cost center
 * (Expense, JournalLine, Bill, ...).
 */
trait HasCostCenter
{
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function scopeForCostCenter(Builder $query, int $costCenterId): Builder
    {
        return $query->where('cost_center_id', $costCenterId);
    }
}
