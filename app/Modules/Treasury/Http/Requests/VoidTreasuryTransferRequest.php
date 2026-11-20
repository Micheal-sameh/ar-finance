<?php

namespace App\Modules\Treasury\Http\Requests;

use App\Modules\Treasury\Models\TreasuryTransfer;
use Illuminate\Foundation\Http\FormRequest;

class VoidTreasuryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', TreasuryTransfer::class);
    }

    public function rules(): array
    {
        return [];
    }
}
