<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Http\Requests\SaveBookingTypeRequest;
use App\Modules\Booking\Models\BookingType;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reusable catalog of booking types (Wedding Pray, Holy Mass, Meeting …).
 * Prices live on the bookable item the type is attached to, not here.
 */
class BookingTypeController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BookingType::class);

        $viewingAllTenants = $this->tenantContext->isViewingAllTenants();

        return Inertia::render('Booking/BookingTypes/Index', [
            'types' => BookingType::query()
                ->withCount('bookableItems')
                ->with('tenant:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (BookingType $type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'bookable_items_count' => $type->bookable_items_count,
                    'tenant_name' => $viewingAllTenants ? $type->tenant?->name : null,
                ]),
            'viewingAllTenants' => $viewingAllTenants,
            'canManage' => $request->user()->can('bookable_items.manage'),
        ]);
    }

    public function store(SaveBookingTypeRequest $request): RedirectResponse
    {
        $this->authorize('create', BookingType::class);

        BookingType::create([
            'tenant_id' => $this->tenantContext->id(),
            'name' => $request->validated('name'),
        ]);

        return redirect()->route('booking-types.index')->with('success', 'Booking type created.');
    }

    public function update(SaveBookingTypeRequest $request, BookingType $bookingType): RedirectResponse
    {
        $this->authorize('update', $bookingType);

        $bookingType->update(['name' => $request->validated('name')]);

        return redirect()->route('booking-types.index')->with('success', 'Booking type updated.');
    }

    public function destroy(BookingType $bookingType): RedirectResponse
    {
        $this->authorize('delete', $bookingType);

        if ($bookingType->bookableItems()->exists() || $bookingType->bookings()->exists()) {
            return redirect()->route('booking-types.index')
                ->with('error', "\"{$bookingType->name}\" cannot be deleted: it is used by a bookable item or a booking.");
        }

        $bookingType->delete();

        return redirect()->route('booking-types.index')->with('success', 'Booking type deleted.');
    }
}
