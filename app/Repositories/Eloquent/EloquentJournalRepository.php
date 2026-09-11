<?php

namespace App\Repositories\Eloquent;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Repositories\Contracts\JournalRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentJournalRepository implements JournalRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return JournalEntry::query()
            ->with(['createdBy', 'lines.account'])
            ->when($filters['source_type'] ?? null, fn ($query, $type) => $query->where('source_type', $type))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('date', '<=', $to))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?JournalEntry
    {
        return JournalEntry::query()->with(['createdBy', 'lines.account', 'lines.costCenter'])->find($id);
    }

    public function create(array $attributes, array $lines): JournalEntry
    {
        $entry = JournalEntry::create($attributes);
        $entry->lines()->createMany($lines);

        return $entry->load(['lines.account']);
    }

    public function postedLinesWithAccounts(?string $from = null, ?string $to = null): Collection
    {
        return JournalLine::query()
            ->with(['account', 'costCenter'])
            ->whereHas('journalEntry', function ($query) use ($from, $to) {
                $query->whereNotNull('posted_at')
                    ->when($from, fn ($query, $value) => $query->whereDate('date', '>=', $value))
                    ->when($to, fn ($query, $value) => $query->whereDate('date', '<=', $value));
            })
            ->get();
    }

    public function postedLinesForAccount(int $accountId, ?string $from = null, ?string $to = null): Collection
    {
        return JournalLine::query()
            ->with('journalEntry')
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($query) use ($from, $to) {
                $query->whereNotNull('posted_at')
                    ->when($from, fn ($query, $value) => $query->whereDate('date', '>=', $value))
                    ->when($to, fn ($query, $value) => $query->whereDate('date', '<=', $value));
            })
            ->get();
    }

    public function existsForSourceInDateRange(string $sourceType, int $sourceId, string $from, string $to): bool
    {
        return JournalEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereNotNull('posted_at')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->exists();
    }

    public function entriesForSource(string $sourceType, int $sourceId): Collection
    {
        return JournalEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereNotNull('posted_at')
            ->orderBy('date')
            ->get();
    }
}
