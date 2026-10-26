<?php

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Models\BookableItem;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row on the Bookable Items page, with the booking types it offers and
 * what each costs there.
 *
 * @mixin BookableItem
 */
class BookableItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->toArray(),
            'booking_types' => $this->bookingTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'rate_type' => $type->pivot->rate_type,
                'price' => $type->pivot->price,
            ])->values(),
            'tenant_id' => $this->tenant_id,
            'tenant_name' => app(TenantContext::class)->isViewingAllTenants() ? $this->tenant?->name : null,
        ];
    }
}
