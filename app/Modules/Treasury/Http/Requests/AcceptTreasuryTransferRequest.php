<?php

namespace App\Modules\Treasury\Http\Requests;

use App\Modules\Treasury\Models\TreasuryTransfer;
use App\Rules\ExistsInTenant;
use Illuminate\Foundation\Http\FormRequest;

class AcceptTreasuryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', TreasuryTransfer::class);
    }

    public function rules(): array
    {
        return [
            'accepted_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'treasury_account_id' => ['required', new ExistsInTenant('accounts')],
        ];
    }

    public function acceptedAmount(): float
    {
        return round((float) $this->validated('accepted_amount'), 2);
    }

    public function treasuryAccountId(): int
    {
        return (int) $this->validated('treasury_account_id');
    }
}
