<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class DevSwitchUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app()->environment('local');
    }

    public function rules(): array
    {
        return [];
    }
}
