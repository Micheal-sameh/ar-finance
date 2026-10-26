<?php

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Models\BookableItem;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookableItem
 */
class BookableItemOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_exclusive' => $this->is_exclusive,
            'deposit_percent' => $this->deposit_percent,
            'tenant_name' => app(TenantContext::class)->isViewingAllTenants() ? $this->tenant?->name : null,
            'booking_types' => $this->bookingTypes->map(fn ($type) => ['id' => $type->id, 'name' => $type->name, 'rate_type' => $type->pivot->rate_type, 'price' => $type->pivot->price])->values(),
        ];
    }
}
