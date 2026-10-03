<?php

namespace App\Repositories\Eloquent;

use App\Models\Account;
use App\Models\CostCenter;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Repositories\Contracts\JournalRepositoryInterface;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentJournalRepository implements JournalRepositoryInterface
{
    /**
     * @var array<string, Collection>
     */
    private array $postedLinesWithAccountsCache = [];

    /**
     * @var array<string, Collection>
     */
    private array $accountsCache = [];

    /**
     * @var array<string, Collection>
     */
    private array $costCentersCache = [];

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return JournalEntry::query()
            ->with(['createdBy', 'lines.account', 'tenant:id,name'])
            ->when($filters['source_type'] ?? null, fn ($query, $type) => $query->where('source_type', $type))
            ->when($filters['created_by'] ?? null, fn ($query, $createdBy) => $query->where('created_by', $createdBy))
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
        $key = $this->tenantCacheKey().'|'.($from ?? '').'|'.($to ?? '');

        if (array_key_exists($key, $this->postedLinesWithAccountsCache)) {
            return $this->postedLinesWithAccountsCache[$key];
        }

        $lines = JournalLine::query()
            ->whereHas('journalEntry', function ($query) use ($from, $to) {
                $query->whereNotNull('posted_at')
                    ->when($from, fn ($query, $value) => $query->whereDate('date', '>=', $value))
                    ->when($to, fn ($query, $value) => $query->whereDate('date', '<=', $value));
            })
            ->get();

        $accounts = $this->accounts();
        $costCenters = $this->costCenters();

        $lines->each(function (JournalLine $line) use ($accounts, $costCenters) {
            $line->setRelation('account', $accounts->get($line->account_id));
            $line->setRelation('costCenter', $line->cost_center_id ? $costCenters->get($line->cost_center_id) : null);
        });

        return $this->postedLinesWithAccountsCache[$key] = $lines;
    }

    private function accounts(): Collection
    {
        $key = $this->tenantCacheKey();

        return $this->accountsCache[$key] ??= Account::all()->keyBy('id');
    }

    private function costCenters(): Collection
    {
        $key = $this->tenantCacheKey();

        return $this->costCentersCache[$key] ??= CostCenter::all()->keyBy('id');
    }

    /**
     * Distinguishes per-tenant cached query results from one another so a
     * platform admin looping over every tenant in a single request (see
     * PlatformReportService::byTenant()) doesn't get tenant A's cached
     * lines/accounts served back for tenant B.
     */
    private function tenantCacheKey(): string
    {
        return (string) $this->tenantContext->id();
    }

    public function postedLinesForAccount(int $accountId, ?string $from = null, ?string $to = null, int|string|null $costCenterFilter = null): Collection
    {
        return JournalLine::query()
            ->with('journalEntry')
            ->where('account_id', $accountId)
            ->when(
                $costCenterFilter === 'unassigned',
                fn ($query) => $query->whereNull('cost_center_id'),
                fn ($query) => $query->when($costCenterFilter !== null, fn ($query) => $query->where('cost_center_id', $costCenterFilter)),
            )
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
