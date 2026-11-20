<?php

namespace App\Modules\Treasury\Http\Requests;

use App\Modules\Treasury\Models\TreasuryTransfer;
use Illuminate\Foundation\Http\FormRequest;

class RejectTreasuryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', TreasuryTransfer::class);
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function reason(): ?string
    {
        $reason = trim((string) $this->input('reason'));

        return $reason === '' ? null : $reason;
    }
}
