<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Exceptions\BookingConflictException;
use App\Modules\Booking\Http\Requests\StoreBookingRequest;
use App\Modules\Booking\Http\Resources\BookingEventResource;
use App\Modules\Booking\Models\BookableItem;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
use App\Rules\ExistsInTenant;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Booking::class);

        return Inertia::render('Booking/Calendar/Index', [
            'viewingAllTenants' => $this->tenantContext->isViewingAllTenants(),
        ]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Booking::class);

        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
            'bookable_item_id' => ['nullable', new ExistsInTenant('bookable_items')],
        ]);

        $bookings = $this->bookings->calendar(
            $request->string('from')->value(),
            $request->string('to')->value(),
            $request->integer('bookable_item_id') ?: null,
        );

        return BookingEventResource::collection($bookings)->response();
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $data = $request->toDto();
        $item = BookableItem::query()->where('is_active', true)->findOrFail($data->bookableItemId);

        try {
            $booking = $this->bookings->create($data, $item, $request->user()->id);
        } catch (BookingConflictException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $count = $this->bookings->seriesSize($booking);

        return back()->with('success', $count > 1 ? "Booked and paid {$count} times." : 'Booked and paid.');
    }

    public function cancel(Booking $booking): RedirectResponse
    {
        $this->authorize('cancel', $booking);

        try {
            $this->bookings->cancel($booking);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Booking cancelled.');
    }
}
