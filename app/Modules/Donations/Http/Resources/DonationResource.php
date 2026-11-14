<?php

namespace App\Modules\Donations\Http\Resources;

use App\Modules\Donations\Enums\DonationStatus;
use App\Modules\Donations\Models\Donation;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Donation
 */
class DonationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'receipt_number' => str_pad((string) $this->receipt_number, 6, '0', STR_PAD_LEFT),
            'donated_at' => $this->donated_at->toDateString(),
            'donor_id' => $this->donor_id,
            'donor_name' => $this->donor?->name,
            'cost_center_id' => $this->cost_center_id,
            'cost_center_name' => $this->costCenter?->name,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'base_amount' => $this->base_amount,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'payment_reference' => $this->payment_reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'notes' => $this->notes,
            'created_by_name' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'tenant_name' => app(TenantContext::class)->isViewingAllTenants() ? $this->tenant?->name : null,
            'can_cancel' => $this->status === DonationStatus::Received && $user->can('cancel', $this->resource),
            'can_print' => $this->status === DonationStatus::Received && $user->can('view', $this->resource),
        ];
    }
}
