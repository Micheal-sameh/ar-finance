<?php

namespace App\Models;

use App\Enums\GlMappingRole;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlMapping extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'role',
        'account_id',    ];

    protected $casts = [
        'role' => GlMappingRole::class,
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
