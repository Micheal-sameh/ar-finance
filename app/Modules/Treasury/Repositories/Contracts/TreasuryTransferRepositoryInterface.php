<?php

namespace App\Modules\Treasury\Repositories\Contracts;

use App\Models\JournalEntry;
use App\Models\Tenant;
use App\Modules\Treasury\Models\TreasuryTransfer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Treasury transfers sit in two ledgers at once, so none of these queries use
 * the tenant scope: the ones that list or find transfers narrow them to what
 * the current tenant may see (everything for the treasury, only its own rows
 * for any other tenant, everything for a Platform Admin viewing all tenants).
 */
interface TreasuryTransferRepositoryInterface
{
    /**
     * @param  array{type?: string|null, status?: string|null, tenant_id?: int|null, from?: string|null, to?: string|null, search?: string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function find(int $id): ?TreasuryTransfer;

    /**
     * Re-reads the transfer under a row lock so two treasurers deciding the
     * same request cannot both post it.
     */
    public function findForUpdate(int $id): ?TreasuryTransfer;

    public function create(array $attributes): TreasuryTransfer;

    public function update(TreasuryTransfer $transfer, array $attributes): TreasuryTransfer;

    public function treasuryTenant(): ?Tenant;

    /**
     * Money the treasury currently holds for a tenant: accepted deposits
     * minus accepted withdrawals.
     */
    public function heldFor(int $tenantId): float;

    /**
     * Funding a tenant still owes the treasury: accepted funding minus
     * accepted repayments.
     */
    public function advancedTo(int $tenantId): float;

    /**
     * Held and advanced amounts for every tenant that has any.
     *
     * @return array<int, array{tenant_id: int, held: float, advanced: float}>
     */
    public function positionsByTenant(): array;

    /**
     * Raw posted debit minus credit of one account, whichever tenant owns it.
     */
    public function ledgerBalance(int $accountId): float;

    /**
     * Every journal entry posted for the transfer, in both ledgers.
     *
     * @return Collection<int, JournalEntry>
     */
    public function journalEntries(TreasuryTransfer $transfer): Collection;
}
