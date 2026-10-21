<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Exceptions\BookingTypeInUseException;
use App\Modules\Booking\Http\Requests\SaveBookingTypeRequest;
use App\Modules\Booking\Http\Resources\BookingTypeResource;
use App\Modules\Booking\Models\BookingType;
use App\Modules\Booking\Services\BookingTypeService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingTypeController extends Controller
{
    public function __construct(
        private readonly BookingTypeService $types,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BookingType::class);

        return Inertia::render('Booking/BookingTypes/Index', [
            'types' => BookingTypeResource::collection($this->types->allWithUsage())->resolve(),
            'viewingAllTenants' => $this->tenantContext->isViewingAllTenants(),
            'canManage' => $request->user()->can('bookable_items.manage'),
        ]);
    }

    public function store(SaveBookingTypeRequest $request): RedirectResponse
    {
        $this->authorize('create', BookingType::class);

        $this->types->create($request->validated('name'));

        return redirect()->route('booking-types.index')->with('success', 'Booking type created.');
    }

    public function update(SaveBookingTypeRequest $request, BookingType $bookingType): RedirectResponse
    {
        $this->authorize('update', $bookingType);

        $this->types->update($bookingType, $request->validated('name'));

        return redirect()->route('booking-types.index')->with('success', 'Booking type updated.');
    }

    public function destroy(BookingType $bookingType): RedirectResponse
    {
        $this->authorize('delete', $bookingType);

        try {
            $this->types->delete($bookingType);
        } catch (BookingTypeInUseException $e) {
            return redirect()->route('booking-types.index')->with('error', $e->getMessage());
        }

        return redirect()->route('booking-types.index')->with('success', 'Booking type deleted.');
    }
}
