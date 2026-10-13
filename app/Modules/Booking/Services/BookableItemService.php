<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\DTOs\CreateBookableItemData;
use App\Modules\Booking\Exceptions\BookableItemInUseException;
use App\Modules\Booking\Models\BookableItem;
use App\Modules\Booking\Repositories\Contracts\BookableItemRepositoryInterface;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

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
        return $this->items->create([
            'tenant_id' => app(TenantContext::class)->id(),
            'name' => $data->name,
            'description' => $data->description,
            'is_exclusive' => $data->isExclusive,
            'rate_type' => $data->rateType,
            'price' => $data->price,
            'deposit_percent' => $data->depositPercent,
            'account_id' => $data->accountId,
            'deferred_revenue_account_id' => $data->deferredRevenueAccountId,
            'deposit_payment_account_id' => $data->depositPaymentAccountId,
            'cost_center_id' => $data->costCenterId,
            'is_active' => $data->isActive,
        ]);
    }

    public function update(BookableItem $item, CreateBookableItemData $data): BookableItem
    {
        return $this->items->update($item, [
            'name' => $data->name,
            'description' => $data->description,
            'is_exclusive' => $data->isExclusive,
            'rate_type' => $data->rateType,
            'price' => $data->price,
            'deposit_percent' => $data->depositPercent,
            'account_id' => $data->accountId,
            'deferred_revenue_account_id' => $data->deferredRevenueAccountId,
            'deposit_payment_account_id' => $data->depositPaymentAccountId,
            'cost_center_id' => $data->costCenterId,
            'is_active' => $data->isActive,
        ]);
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
