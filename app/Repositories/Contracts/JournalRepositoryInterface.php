<?php

namespace App\Repositories\Contracts;

use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface JournalRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function find(int $id): ?JournalEntry;

    /**
     * Create the entry and its lines as one write.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function create(array $attributes, array $lines): JournalEntry;

    /**
     * All posted journal lines in a date range, with account eager-loaded.
     * Single query — callers (Trial Balance, GL) group in PHP, never loop-query.
     */
    public function postedLinesWithAccounts(?string $from = null, ?string $to = null): Collection;

    public function postedLinesForAccount(int $accountId, ?string $from = null, ?string $to = null): Collection;
}
