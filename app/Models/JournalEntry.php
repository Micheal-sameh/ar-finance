<?php

namespace App\Models;

use App\Enums\JournalSourceType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'date',
        'description',
        'reference',
        'source_type',
        'source_id',
        'created_by',
        'posted_at',
    ];

    protected $casts = [
        'date' => 'date',
        'source_type' => JournalSourceType::class,
        'posted_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }
}
