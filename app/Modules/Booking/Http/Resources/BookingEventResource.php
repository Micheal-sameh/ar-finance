<?php

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Models\Booking;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a booking as a FullCalendar event for the calendar feed.
 *
 * @mixin Booking
 */
class BookingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // A Platform Admin browsing "All tenants" sees every tenant's
        // bookings combined and read-only — same convention as every other
        // cross-tenant list in this app — since booking/cancelling/paying
        // still require impersonating the specific tenant.
        $viewingAllTenants = app(TenantContext::class)->isViewingAllTenants();

        return [
            'id' => $this->id,
            'title' => $this->bookingType ? "{$this->bookableItem->name} · {$this->bookingType->name} · {$this->user->name}" : "{$this->bookableItem->name} · {$this->user->name}",
            'start' => $this->starts_at->toIso8601String(),
            'end' => $this->ends_at->toIso8601String(),
            'status' => $this->status->value,
            'bookable_item_id' => $this->bookable_item_id,
            'recurrence' => $this->recurrence->value,
            'recurrence_until' => $this->recurrence_until?->toDateString(),
            'booking_type_name' => $this->bookingType?->name,
            'is_exclusive' => $this->bookableItem->is_exclusive,
            'price' => $this->price,
            'discount_type' => $this->discount_type?->value,
            'discount_value' => $this->discount_value,
            'discount_amount' => $this->discount_amount,
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),
            'balance_due' => $this->balanceDue(),
            'tenant_name' => $viewingAllTenants ? $this->tenant?->name : null,
            'tenant_id' => $this->tenant_id,
            'can_cancel' => $request->user()->can('cancel', $this->resource),
            'is_mine' => $this->user_id === $request->user()->id,
        ];
    }
}
