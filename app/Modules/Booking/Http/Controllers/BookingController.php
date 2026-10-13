<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Enums\BookingStatus;
use App\Modules\Booking\Exceptions\BookingConflictException;
use App\Modules\Booking\Http\Requests\MarkBookingPaidRequest;
use App\Modules\Booking\Http\Requests\PayBookingDepositRequest;
use App\Modules\Booking\Http\Requests\StoreBookingRequest;
use App\Modules\Booking\Http\Resources\BookingEventResource;
use App\Modules\Booking\Models\BookableItem;
use App\Modules\Booking\Models\Booking;
use App\Modules\Booking\Services\BookingService;
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
            'bookable_item_id' => ['nullable', 'exists:bookable_items,id'],
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
            $this->bookings->create($data, $item, $request->user()->id);
        } catch (BookingConflictException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Booked.');
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

    public function markPaid(MarkBookingPaidRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $settled = $this->bookings->markPaid(
                $booking,
                (int) $request->validated('payment_account_id'),
                $request->filled('amount') ? (float) $request->validated('amount') : null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = $settled->status === BookingStatus::Paid ? 'Booking marked as paid.' : 'Payment collected — balance still due.';

        return back()->with('success', $message);
    }

    public function payDeposit(PayBookingDepositRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $this->bookings->payDeposit(
                $booking,
                (int) $request->validated('payment_account_id'),
                $request->validated('payment_due_at'),
                $request->filled('deposit_amount') ? (float) $request->validated('deposit_amount') : null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Deposit recorded.');
    }
}
