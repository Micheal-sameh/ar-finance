<?php

namespace App\Rules;

use App\Support\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Tenant-aware replacement for `exists:{table},id`. The built-in rule runs
 * a raw query that bypasses TenantScope, so it would accept another
 * tenant's record ids.
 */
class ExistsInTenant implements ValidationRule
{
    public function __construct(private readonly string $table) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tenantId = app(TenantContext::class)->id();

        $exists = $tenantId !== null
            && (is_int($value) || (is_string($value) && ctype_digit($value)))
            && DB::table($this->table)->where('id', $value)->where('tenant_id', $tenantId)->exists();

        if (! $exists) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
