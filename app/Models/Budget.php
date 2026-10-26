<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A service's budget for one calendar year: a monthly amount per revenue or
 * expense account (optionally within one cost center).
 */
class Budget extends Model
{
    use Auditable;
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'year'];

    protected $casts = ['year' => 'integer'];

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }
}
