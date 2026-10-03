<?php

namespace App\Services;

use App\DTOs\CreateJournalEntryData;
use App\Enums\JournalSourceType;
use App\Exceptions\NonPostableAccountException;
use App\Exceptions\UnbalancedJournalEntryException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Repositories\Contracts\JournalRepositoryInterface;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The single writer of the general ledger. Every module that posts money
 * (invoices, expenses, payroll, manual entries, ...) must go through
 * postJournalEntry() so the GL never diverges from module data.
 */
class JournalService
{
    public function __construct(
        private readonly JournalRepositoryInterface $journals,
    ) {}

    /**
     * Validate and post a balanced journal entry in one DB transaction.
     *
     * @throws UnbalancedJournalEntryException
     * @throws NonPostableAccountException
     */
    public function postJournalEntry(CreateJournalEntryData $data): JournalEntry
    {
        $this->assertBalanced($data);
        $this->assertPostable($data);

        return DB::transaction(function () use ($data) {
            $entry = $this->journals->create(
                attributes: [
                    'tenant_id' => app(TenantContext::class)->id(),
                    'date' => $data->date,
                    'description' => $data->description,
                    'reference' => $data->reference,
                    'source_type' => $data->sourceType,
                    'source_id' => $data->sourceId,
                    'created_by' => $data->createdBy,
                    'posted_at' => now(),
                ],
                lines: array_map(fn ($line) => [
                    'account_id' => $line->accountId,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'cost_center_id' => $line->costCenterId,
                    'description' => $line->description,
                ], $data->lines),
            );

            Log::info('Journal entry posted', [
                'journal_entry_id' => $entry->id,
                'source_type' => $data->sourceType->value,
                'source_id' => $data->sourceId,
            ]);

            return $entry;
        });
    }

    /**
     * @throws UnbalancedJournalEntryException
     */
    public function assertBalanced(CreateJournalEntryData $data): void
    {
        [$totalDebit, $totalCredit] = $this->totals($data);

        if (! $this->isBalanced($totalDebit, $totalCredit)) {
            throw UnbalancedJournalEntryException::forTotals($totalDebit, $totalCredit);
        }
    }

    /**
     * A parent account (one with child accounts) is a label for grouping
     * its children on reports — it never carries its own balance, so no
     * journal line may post to it.
     *
     * @throws NonPostableAccountException
     */
    public function assertPostable(CreateJournalEntryData $data): void
    {
        $accountIds = array_unique(array_map(fn ($line) => $line->accountId, $data->lines));

        $parentAccount = Account::query()
            ->whereIn('id', $accountIds)
            ->whereHas('children')
            ->first();

        if ($parentAccount) {
            throw NonPostableAccountException::hasChildren($parentAccount->code);
        }
    }

    public function isBalanced(float $totalDebit, float $totalCredit): bool
    {
        return abs(round($totalDebit - $totalCredit, 2)) < 0.005;
    }

    /**
     * @return array{0: float, 1: float} [totalDebit, totalCredit]
     */
    public function totals(CreateJournalEntryData $data): array
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($data->lines as $line) {
            $totalDebit += $line->debit;
            $totalCredit += $line->credit;
        }

        return [round($totalDebit, 2), round($totalCredit, 2)];
    }

    public function find(int $id): ?JournalEntry
    {
        return $this->journals->find($id);
    }

    public function paginate(array $filters = [], int $perPage = 25)
    {
        return $this->journals->paginate($filters, $perPage);
    }

    /**
     * True if a posted entry already exists for this source in the date
     * range — the guard against double-posting a period.
     */
    public function existsForSourceInDateRange(JournalSourceType $sourceType, int $sourceId, Carbon $from, Carbon $to): bool
    {
        return $this->journals->existsForSourceInDateRange($sourceType->value, $sourceId, $from->toDateString(), $to->toDateString());
    }

    /**
     * @return Collection<int, JournalEntry>
     */
    public function entriesForSource(JournalSourceType $sourceType, int $sourceId): Collection
    {
        return $this->journals->entriesForSource($sourceType->value, $sourceId);
    }
}
