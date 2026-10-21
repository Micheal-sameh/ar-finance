<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\DTOs\CreateBookableItemData;
use App\Modules\Booking\Exceptions\BookableItemInUseException;
use App\Modules\Booking\Models\BookableItem;
use App\Modules\Booking\Repositories\Contracts\BookableItemRepositoryInterface;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BookableItemService
{
    public function __construct(
        private readonly BookableItemRepositoryInterface $items,
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
            $item = $this->items->create($this->attributes($data) + ['tenant_id' => app(TenantContext::class)->id()]);

            $this->syncBookingTypes($item, $data->bookingTypes);

            return $item;
        });
    }

    public function update(BookableItem $item, CreateBookableItemData $data): BookableItem
    {
        return DB::transaction(function () use ($item, $data) {
            $item = $this->items->update($item, $this->attributes($data));

            $this->syncBookingTypes($item, $data->bookingTypes);

            return $item;
        });
    }

    /**
     * Makes the item's offered booking types (and their prices) match the
     * submitted list; types left out are detached. Bookings keep their
     * snapshotted price.
     *
     * @param  array<int, array{booking_type_id: int, rate_type: string, price: float}>  $types
     */
    private function syncBookingTypes(BookableItem $item, array $types): void
    {
        $item->bookingTypes()->sync(
            collect($types)->mapWithKeys(fn (array $type) => [$type['booking_type_id'] => ['rate_type' => $type['rate_type'], 'price' => $type['price']]])->all(),
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
            'deposit_percent' => $data->depositPercent,
            'account_id' => $data->accountId,
            'deferred_revenue_account_id' => $data->deferredRevenueAccountId,
            'deposit_payment_account_id' => $data->depositPaymentAccountId,
            'cost_center_id' => $data->costCenterId,
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
