<?php

namespace App\Modules\Treasury\Http\Requests;

use App\Modules\Treasury\DTOs\TreasuryTransferData;
use App\Modules\Treasury\Enums\TreasuryTransferType;
use App\Modules\Treasury\Models\TreasuryTransfer;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class MoveTreasuryFundsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', TreasuryTransfer::class);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'treasury_account_id' => ['required', new ExistsInTenant('accounts')],
            'counter_account_id' => ['required', 'different:treasury_account_id', new ExistsInTenant('accounts')],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toDto(): TreasuryTransferData
    {
        return TreasuryTransferData::fromArray(TreasuryTransferType::Move, $this->validated());
    }
}
