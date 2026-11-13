<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\DTOs\CreateBookableItemData;
use App\Modules\Booking\Exceptions\BookableItemInUseException;
use App\Modules\Booking\Models\BookableItem;
use App\Modules\Booking\Repositories\Contracts\BookableItemRepositoryInterface;
use App\Services\GlMappingService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BookableItemService
{
    public function __construct(
        private readonly BookableItemRepositoryInterface $items,
        private readonly GlMappingService $glMapping,
    ) {}

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->items->paginate($filters, $perPage);
    }

    public function all(): Collection
    {
        return $this->items->all();
    }

    public function find(int $id): ?BookableItem
    {
        return $this->items->find($id);
    }

    public function create(CreateBookableItemData $data): BookableItem
    {
        return DB::transaction(function () use ($data) {
            $defaults = $this->glMapping->defaults();

            $item = $this->items->create([
                ...$this->attributes($data),
                'account_id' => $data->accountId ?? $defaults['booking_revenue'] ?? null,
                'cost_center_id' => $data->costCenterId,
                'tenant_id' => app(TenantContext::class)->id(),
            ]);

            $this->syncBookingTypes($item, $data);

            return $item;
        });
    }

    public function update(BookableItem $item, CreateBookableItemData $data): BookableItem
    {
        return DB::transaction(function () use ($item, $data) {
            $item = $this->items->update($item, $this->attributes($data) + ($data->includesAccounting ? [
                'account_id' => $data->accountId,
                'cost_center_id' => $data->costCenterId,
            ] : []));

            $this->syncBookingTypes($item, $data);

            return $item;
        });
    }

    /**
     * Makes the item's offered booking types (and their prices) match the
     * submitted list; types left out are detached. Bookings keep their
     * snapshotted price. When the editor can't set accounting, each type
     * keeps the revenue account and cost center it already had.
     */
    private function syncBookingTypes(BookableItem $item, CreateBookableItemData $data): void
    {
        $existing = $item->bookingTypes()->get()->keyBy('id');

        $item->bookingTypes()->sync(
            collect($data->bookingTypes)->mapWithKeys(function (array $type) use ($data, $existing) {
                $current = $existing->get($type['booking_type_id'])?->pivot;

                return [$type['booking_type_id'] => [
                    'rate_type' => $type['rate_type'],
                    'price' => $type['price'],
                    'account_id' => $data->includesAccounting ? $type['account_id'] : $current?->account_id,
                    'cost_center_id' => $data->includesAccounting ? $type['cost_center_id'] : $current?->cost_center_id,
                ]];
            })->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(CreateBookableItemData $data): array
    {
        return [
            'name' => $data->name,
            'description' => $data->description,
            'is_exclusive' => $data->isExclusive,
            'is_active' => $data->isActive,
        ];
    }

    /**
     * @throws BookableItemInUseException
     */
    public function delete(BookableItem $item): void
    {
        if ($this->items->isInUse($item)) {
            throw BookableItemInUseException::hasBookings($item->name);
        }

        $this->items->delete($item);
    }
}
