<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

/**
 * Blocks granting the Platform Admin role to anyone unless the acting
 * user already holds it themselves — Portal Manager shares every other
 * permission but must never be able to escalate someone to Platform Admin.
 */
class GrantableRole implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === 'Platform Admin' && ! Auth::user()->hasRole('Platform Admin')) {
            $fail('Only a Platform Admin can grant the Platform Admin role.');
        }
    }
}
