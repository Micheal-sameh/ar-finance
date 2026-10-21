<?php

namespace App\Modules\Booking\Repositories\Contracts;

use App\Modules\Booking\Models\BookableItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface BookableItemRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * All active items, for the booking calendar's item picker.
     */
    public function all(): Collection;

    public function find(int $id): ?BookableItem;

    public function create(array $attributes): BookableItem;

    public function update(BookableItem $item, array $attributes): BookableItem;

    public function delete(BookableItem $item): bool;

    public function isInUse(BookableItem $item): bool;
}
