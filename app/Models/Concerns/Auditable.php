<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Writes a before/after snapshot to audit_logs on create/update/delete.
 * Mix into every financially-sensitive model (Account, JournalEntry, ...).
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->recordAudit('created', null, $model->getAttributes()));

        static::updated(fn ($model) => $model->recordAudit('updated', $model->getOriginal(), $model->getChanges()));

        static::deleted(fn ($model) => $model->recordAudit('deleted', $model->getOriginal(), null));
    }

    protected function recordAudit(string $action, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'tenant_id' => $this->tenant_id ?? null,
            'user_id' => auth()->id(),
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
