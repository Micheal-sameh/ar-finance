<?php

namespace App\Modules\Donations\Repositories\Contracts;

use App\Models\JournalEntry;
use App\Models\User;
use App\Modules\Donations\Models\Donation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface DonationRepositoryInterface
{
    /**
     * @param  array{search?: string|null, from?: string|null, to?: string|null, cost_center_id?: int|null, currency?: string|null, payment_method?: string|null, status?: string|null, created_by?: int|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function find(int $id): ?Donation;

    public function create(array $attributes): Donation;

    public function update(Donation $donation, array $attributes): Donation;

    /**
     * The next receipt number for the current tenant, taken under a lock so
     * two donations recorded together never share one.
     */
    public function nextReceiptNumber(): int;

    /**
     * Every journal entry posted for the donation, with its lines.
     *
     * @return Collection<int, JournalEntry>
     */
    public function journalEntries(Donation $donation): Collection;

    /**
     * People who recorded donations, for the "created by" filter.
     *
     * @return Collection<int, User>
     */
    public function creators(): Collection;
}
