<?php

namespace App\Modules\Treasury\Http\Resources;

use App\Modules\Treasury\Models\TreasuryTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TreasuryTransfer
 */
class TreasuryTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canManage = $request->user()->can('manage', TreasuryTransfer::class);

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->tenant?->name,
            'transfer_date' => $this->transfer_date->toDateString(),
            'requested_amount' => $this->requested_amount,
            'accepted_amount' => $this->accepted_amount,
            'tenant_account' => $this->tenantAccount ? "{$this->tenantAccount->code} {$this->tenantAccount->name}" : null,
            'treasury_account_id' => $this->treasury_account_id,
            'treasury_account' => $this->treasuryAccount ? "{$this->treasuryAccount->code} {$this->treasuryAccount->name}" : null,
            'counter_account' => $this->counterAccount ? "{$this->counterAccount->code} {$this->counterAccount->name}" : null,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'reject_reason' => $this->reject_reason,
            'requested_by' => $this->requester?->name,
            'decided_by' => $this->decider?->name,
            'decided_at' => $this->decided_at?->toDateTimeString(),
            'can_decide' => $canManage && $this->status->isDecidable(),
            'can_void' => $canManage && $this->status->isVoidable(),
        ];
    }
}
