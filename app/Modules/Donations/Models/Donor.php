<?php

namespace App\Modules\Donations\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donor extends Model
{
    use Auditable;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'notes',
    ];

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }
}
