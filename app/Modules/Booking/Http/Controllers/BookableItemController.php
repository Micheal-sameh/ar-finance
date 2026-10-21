<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Exceptions\BookableItemInUseException;
use App\Modules\Booking\Http\Requests\SaveBookableItemRequest;
use App\Modules\Booking\Http\Resources\BookableItemOptionResource;
use App\Modules\Booking\Models\BookableItem;
use App\Modules\Booking\Models\BookingType;
use App\Modules\Booking\Services\BookableItemService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;

class BookableItemController extends Controller
{
    public function __construct(
        private readonly BookableItemService $items,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BookableItem::class);

        $viewingAllTenants = $this->tenantContext->isViewingAllTenants();

        $items = $this->items->paginate($request->only(['search']))
            ->through(fn (BookableItem $item) => [
                ...$item->toArray(),
                'booking_types' => $item->bookingTypes->map(fn (BookingType $type) => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'rate_type' => $type->pivot->rate_type,
                    'price' => $type->pivot->price,
                ])->values(),
                'tenant_name' => $viewingAllTenants ? $item->tenant?->name : null,
            ]);

        return Inertia::render('Booking/BookableItems/Index', [
            'items' => $items,
            'bookingTypeOptions' => BookingType::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search']),
            'viewingAllTenants' => $viewingAllTenants,
            'canManage' => $request->user()->can('bookable_items.manage'),
        ]);
    }

    public function options(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BookableItem::class);

        return BookableItemOptionResource::collection($this->items->all());
    }

    public function store(SaveBookableItemRequest $request): RedirectResponse
    {
        $this->items->create($request->toDto());

        return redirect()->route('bookable-items.index')->with('success', 'Bookable item created.');
    }

    public function update(SaveBookableItemRequest $request, BookableItem $bookableItem): RedirectResponse
    {
        $this->authorize('update', $bookableItem);

        $this->items->update($bookableItem, $request->toDto());

        return redirect()->route('bookable-items.index')->with('success', 'Bookable item updated.');
    }

    public function destroy(BookableItem $bookableItem): RedirectResponse
    {
        $this->authorize('delete', $bookableItem);

        try {
            $this->items->delete($bookableItem);
        } catch (BookableItemInUseException $e) {
            return redirect()->route('bookable-items.index')->with('error', $e->getMessage());
        }

        return redirect()->route('bookable-items.index')->with('success', 'Bookable item deleted.');
    }
}
