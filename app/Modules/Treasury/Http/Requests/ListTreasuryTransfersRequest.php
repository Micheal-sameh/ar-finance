<?php

namespace App\Modules\Treasury\Http\Requests;

use App\Modules\Treasury\Enums\TreasuryTransferStatus;
use App\Modules\Treasury\Enums\TreasuryTransferType;
use App\Modules\Treasury\Models\TreasuryTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListTreasuryTransfersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', TreasuryTransfer::class);
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', new Enum(TreasuryTransferType::class)],
            'status' => ['nullable', new Enum(TreasuryTransferStatus::class)],
            'tenant_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'type' => $this->filled('type') ? (string) $this->input('type') : null,
            'status' => $this->filled('status') ? (string) $this->input('status') : null,
            'tenant_id' => $this->filled('tenant_id') ? (int) $this->input('tenant_id') : null,
            'from' => $this->filled('from') ? (string) $this->input('from') : null,
            'to' => $this->filled('to') ? (string) $this->input('to') : null,
            'search' => $this->filled('search') ? (string) $this->input('search') : null,
        ];
    }
}
