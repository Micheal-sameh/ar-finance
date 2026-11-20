<?php

namespace App\Modules\Treasury\Repositories\Eloquent;

use App\Enums\JournalSourceType;
use App\Models\JournalEntry;
use App\Models\Tenant;
use App\Modules\Treasury\Enums\TreasuryTransferStatus;
use App\Modules\Treasury\Enums\TreasuryTransferType;
use App\Modules\Treasury\Models\TreasuryTransfer;
use App\Modules\Treasury\Repositories\Contracts\TreasuryTransferRepositoryInterface;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EloquentTreasuryTransferRepository implements TreasuryTransferRepositoryInterface
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->visible()
            ->with(['tenant:id,name', 'requester:id,name', 'decider:id,name', 'tenantAccount:id,code,name', 'treasuryAccount:id,code,name', 'counterAccount:id,code,name'])
            ->when($filters['type'] ?? null, fn (Builder $query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['tenant_id'] ?? null, fn (Builder $query, $id) => $query->where('tenant_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->whereDate('transfer_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->whereDate('transfer_date', '<=', $to))
            ->when($filters['search'] ?? null, fn (Builder $query, $search) => $query->where(function (Builder $query) use ($search) {
                $query->where('reference', 'like', "%{$search}%")->orWhere('notes', 'like', "%{$search}%");
            }))
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?TreasuryTransfer
    {
        return $this->visible()
            ->with(['tenant:id,name', 'requester:id,name', 'decider:id,name', 'tenantAccount', 'treasuryAccount', 'counterAccount'])
            ->find($id);
    }

    public function findForUpdate(int $id): ?TreasuryTransfer
    {
        return $this->visible()->lockForUpdate()->find($id);
    }

    public function create(array $attributes): TreasuryTransfer
    {
        return TreasuryTransfer::create($attributes);
    }

    public function update(TreasuryTransfer $transfer, array $attributes): TreasuryTransfer
    {
        $transfer->update($attributes);

        return $transfer;
    }

    public function treasuryTenant(): ?Tenant
    {
        return Tenant::query()->where('is_treasury', true)->where('is_active', true)->first();
    }

    public function heldFor(int $tenantId): float
    {
        return $this->net($tenantId, TreasuryTransferType::Deposit, TreasuryTransferType::Withdrawal);
    }

    public function advancedTo(int $tenantId): float
    {
        return $this->net($tenantId, TreasuryTransferType::Funding, TreasuryTransferType::Repayment);
    }

    public function positionsByTenant(): array
    {
        $rows = TreasuryTransfer::query()
            ->where('status', TreasuryTransferStatus::Accepted->value)
            ->whereNotNull('tenant_id')
            ->groupBy('tenant_id', 'type')
            ->select('tenant_id', 'type', DB::raw('SUM(accepted_amount) as total'))
            ->get();

        $positions = [];

        foreach ($rows as $row) {
            $positions[$row->tenant_id] ??= ['tenant_id' => (int) $row->tenant_id, 'held' => 0.0, 'advanced' => 0.0];
            $total = (float) $row->total;

            match ($row->type) {
                TreasuryTransferType::Deposit => $positions[$row->tenant_id]['held'] += $total,
                TreasuryTransferType::Withdrawal => $positions[$row->tenant_id]['held'] -= $total,
                TreasuryTransferType::Funding => $positions[$row->tenant_id]['advanced'] += $total,
                TreasuryTransferType::Repayment => $positions[$row->tenant_id]['advanced'] -= $total,
                default => null,
            };
        }

        return array_values($positions);
    }

    public function ledgerBalance(int $accountId): float
    {
        $totals = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $accountId)
            ->whereNotNull('journal_entries.posted_at')
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) as debit, COALESCE(SUM(journal_lines.credit), 0) as credit')
            ->first();

        return round((float) $totals->debit - (float) $totals->credit, 2);
    }

    public function journalEntries(TreasuryTransfer $transfer): Collection
    {
        return JournalEntry::query()
            ->withoutGlobalScopes()
            ->with('lines')
            ->where('source_type', JournalSourceType::Treasury->value)
            ->where('source_id', $transfer->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Builder<TreasuryTransfer>
     */
    private function visible(): Builder
    {
        $tenantId = $this->tenantContext->id();
        $query = TreasuryTransfer::query();

        if ($tenantId === null) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query->where('treasury_tenant_id', $tenantId)->orWhere('tenant_id', $tenantId));
    }

    private function net(int $tenantId, TreasuryTransferType $plus, TreasuryTransferType $minus): float
    {
        $sum = fn (TreasuryTransferType $type) => (float) TreasuryTransfer::query()
            ->where('tenant_id', $tenantId)
            ->where('type', $type->value)
            ->where('status', TreasuryTransferStatus::Accepted->value)
            ->sum('accepted_amount');

        return round($sum($plus) - $sum($minus), 2);
    }
}
