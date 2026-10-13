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
            'title' => "{$this->bookableItem->name} · {$this->user->name}",
            'start' => $this->starts_at->toIso8601String(),
            'end' => $this->ends_at->toIso8601String(),
            'status' => $this->status->value,
            'bookable_item_id' => $this->bookable_item_id,
            'is_exclusive' => $this->bookableItem->is_exclusive,
            'price' => $this->price,
            'accepts_deposits' => $this->bookableItem->acceptsDeposits(),
            'deposit_percent' => $this->bookableItem->deposit_percent,
            'deposit_amount' => $this->deposit_amount,
            'balance_paid' => $this->balance_paid,
            'deposit_paid_at' => $this->deposit_paid_at?->toIso8601String(),
            'payment_due_at' => $this->payment_due_at?->toIso8601String(),
            'balance_due' => $this->balanceDue(),
            'is_overdue' => $this->isOverdue(),
            'tenant_name' => $viewingAllTenants ? $this->tenant?->name : null,
            'can_cancel' => ! $viewingAllTenants && $request->user()->can('cancel', $this->resource),
            'can_mark_paid' => ! $viewingAllTenants && $request->user()->can('manage', $this->resource),
            'can_pay_deposit' => ! $viewingAllTenants && $request->user()->can('manage', $this->resource),
            'is_mine' => $this->user_id === $request->user()->id,
        ];
    }
}
