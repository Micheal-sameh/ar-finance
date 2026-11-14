<?php

namespace App\Modules\Donations\Repositories\Eloquent;

use App\Enums\JournalSourceType;
use App\Models\JournalEntry;
use App\Models\User;
use App\Modules\Donations\Models\Donation;
use App\Modules\Donations\Repositories\Contracts\DonationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentDonationRepository implements DonationRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Donation::query()
            ->with(['donor', 'costCenter:id,name', 'creator:id,name', 'tenant:id,name'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('payment_reference', 'like', "%{$search}%")
                    ->orWhere('receipt_number', $search)
                    ->orWhereHas('donor', fn ($donor) => $donor->where('name', 'like', "%{$search}%"));
            }))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('donated_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('donated_at', '<=', $to))
            ->when($filters['cost_center_id'] ?? null, fn ($query, $id) => $query->where('cost_center_id', $id))
            ->when($filters['currency'] ?? null, fn ($query, $currency) => $query->where('currency', $currency))
            ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['created_by'] ?? null, fn ($query, $id) => $query->where('created_by', $id))
            ->orderByDesc('donated_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?Donation
    {
        return Donation::query()->with(['donor', 'costCenter', 'creator', 'tenant'])->find($id);
    }

    public function create(array $attributes): Donation
    {
        return Donation::create($attributes);
    }

    public function update(Donation $donation, array $attributes): Donation
    {
        $donation->update($attributes);

        return $donation;
    }

    public function nextReceiptNumber(): int
    {
        return ((int) Donation::query()->lockForUpdate()->max('receipt_number')) + 1;
    }

    public function journalEntries(Donation $donation): Collection
    {
        return JournalEntry::query()
            ->with('lines')
            ->where('source_type', JournalSourceType::Donation->value)
            ->where('source_id', $donation->id)
            ->orderBy('id')
            ->get();
    }

    public function creators(): Collection
    {
        return User::query()
            ->whereIn('id', Donation::query()->select('created_by'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
