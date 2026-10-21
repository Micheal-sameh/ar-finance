<?php

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Models\BookingType;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row on the Booking Types page.
 *
 * @mixin BookingType
 */
class BookingTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bookable_items_count' => $this->bookable_items_count,
            'tenant_name' => app(TenantContext::class)->isViewingAllTenants() ? $this->tenant?->name : null,
        ];
    }
}
