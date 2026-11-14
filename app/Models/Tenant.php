<?php

namespace App\Models;

use App\Enums\TenantModule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'base_currency',
        'is_active',
        'modules',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'modules' => 'array',
    ];

    public function hasModule(TenantModule $module): bool
    {
        return in_array($module->value, $this->modules ?? [], true);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Everyone with access to this tenant, including users whose default
     * tenant is a different one.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
    }
}
