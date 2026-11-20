<?php

namespace App\Modules\Treasury\Http\Requests;

use App\Modules\Treasury\DTOs\TreasuryTransferData;
use App\Modules\Treasury\Enums\TreasuryTransferType;
use App\Modules\Treasury\Models\TreasuryTransfer;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A tenant admin asks the treasurer to take a deposit, pay out money held
 * for the tenant, or take back funding the tenant owes.
 */
class RequestTreasuryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('request', TreasuryTransfer::class);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                TreasuryTransferType::Deposit->value,
                TreasuryTransferType::Withdrawal->value,
                TreasuryTransferType::Repayment->value,
            ])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'tenant_account_id' => ['required', new ExistsInTenant('accounts')],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toDto(): TreasuryTransferData
    {
        $data = $this->validated();

        return TreasuryTransferData::fromArray(TreasuryTransferType::from($data['type']), $data);
    }
}
