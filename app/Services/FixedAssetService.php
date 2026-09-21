<?php

namespace App\Services;

use App\DTOs\CreateFixedAssetData;
use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\JournalSourceType;
use App\Exceptions\DepreciationAlreadyPostedException;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Repositories\Contracts\FixedAssetRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FixedAssetService
{
    public function __construct(
        private readonly FixedAssetRepositoryInterface $fixedAssets,
        private readonly JournalService $journals,
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->fixedAssets->paginate($filters, $perPage);
    }

    public function all(): Collection
    {
        return $this->fixedAssets->all();
    }

    public function find(int $id): ?FixedAsset
    {
        return $this->fixedAssets->find($id);
    }

    public function create(CreateFixedAssetData $data): FixedAsset
    {
        return $this->fixedAssets->create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $data->name,
            'purchase_date' => $data->purchaseDate,
            'cost' => $data->cost,
            'salvage_value' => $data->salvageValue,
            'useful_life_years' => $data->usefulLifeYears,
            'depreciation_method' => $data->depreciationMethod,
            'asset_account_id' => $data->assetAccountId,
            'depreciation_account_id' => $data->depreciationAccountId,
            'accumulated_depreciation_account_id' => $data->accumulatedDepreciationAccountId,
        ]);
    }

    public function delete(FixedAsset $fixedAsset): void
    {
        if ((float) $fixedAsset->accumulated_depreciation > 0) {
            throw new RuntimeException("Fixed asset \"{$fixedAsset->name}\" cannot be deleted: it has posted depreciation history.");
        }

        $this->fixedAssets->delete($fixedAsset);
    }

    /**
     * Posts one month of depreciation: Dr depreciation expense,
     * Cr accumulated depreciation. Skips (throws) if that month is
     * already posted or the asset is fully depreciated, so a batch run
     * can safely call this per-asset without double-posting.
     *
     * @param  string|null  $month  'Y-m', defaults to the current month.
     *
     * @throws DepreciationAlreadyPostedException
     * @throws RuntimeException
     */
    public function postDepreciation(FixedAsset $fixedAsset, ?string $month = null, ?int $createdBy = null): JournalEntry
    {
        $createdBy ??= auth()->id() ?? config('app.system_user_id');

        if ($createdBy === null) {
            throw new RuntimeException('Cannot post depreciation: no authenticated user and no SYSTEM_USER_ID configured.');
        }

        $month ??= now()->format('Y-m');
        $periodStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        if ($this->journals->existsForSourceInDateRange(JournalSourceType::Depreciation, $fixedAsset->id, $periodStart, $periodEnd)) {
            throw DepreciationAlreadyPostedException::forMonth($fixedAsset->name, $month);
        }

        if ($fixedAsset->isFullyDepreciated()) {
            throw new RuntimeException("\"{$fixedAsset->name}\" is already fully depreciated.");
        }

        $amount = min($fixedAsset->monthlyDepreciation(), $fixedAsset->remainingDepreciable());

        return DB::transaction(function () use ($fixedAsset, $periodEnd, $month, $amount, $createdBy) {
            $entry = $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: $periodEnd->toDateString(),
                description: "Depreciation for \"{$fixedAsset->name}\" — {$month}",
                reference: null,
                sourceType: JournalSourceType::Depreciation,
                sourceId: $fixedAsset->id,
                createdBy: $createdBy,
                lines: [
                    new JournalLineData(accountId: $fixedAsset->depreciation_account_id, debit: $amount, credit: 0),
                    new JournalLineData(accountId: $fixedAsset->accumulated_depreciation_account_id, debit: 0, credit: $amount),
                ],
            ));

            $this->fixedAssets->incrementAccumulatedDepreciation($fixedAsset, $amount);

            return $entry;
        });
    }

    /**
     * Runs postDepreciation() for every asset, collecting a result per
     * asset instead of letting one failure abort the batch.
     *
     * @return array<int, array{asset: FixedAsset, posted: bool, amount: float, message: ?string}>
     */
    public function runMonthlyDepreciationForAll(?string $month = null, ?int $createdBy = null): array
    {
        return $this->all()->map(function (FixedAsset $asset) use ($month, $createdBy) {
            try {
                $this->postDepreciation($asset, $month, $createdBy);

                return ['asset' => $asset, 'posted' => true, 'amount' => $asset->monthlyDepreciation(), 'message' => null];
            } catch (RuntimeException $e) {
                return ['asset' => $asset, 'posted' => false, 'amount' => 0.0, 'message' => $e->getMessage()];
            }
        })->values()->all();
    }

    /**
     * Projected schedule from purchase to fully-depreciated, cross-
     * referenced against what's actually posted — for the register's
     * "depreciation schedule" view.
     *
     * @return array<int, array{month: string, amount: float, cumulative: float, book_value: float, posted: bool}>
     */
    public function depreciationSchedule(FixedAsset $fixedAsset): array
    {
        $postedMonths = $this->journals->entriesForSource(JournalSourceType::Depreciation, $fixedAsset->id)
            ->map(fn (JournalEntry $entry) => $entry->date->format('Y-m'))
            ->flip();

        $monthly = $fixedAsset->monthlyDepreciation();
        $base = $fixedAsset->depreciableBase();
        $cursor = $fixedAsset->purchase_date->copy()->startOfMonth()->addMonth();
        $cumulative = 0.0;
        $schedule = [];

        for ($i = 0; $i < $fixedAsset->useful_life_years * 12 && $cumulative < $base; $i++) {
            $amount = min($monthly, round($base - $cumulative, 2));
            $cumulative = round($cumulative + $amount, 2);
            $label = $cursor->format('Y-m');

            $schedule[] = [
                'month' => $label,
                'amount' => $amount,
                'cumulative' => $cumulative,
                'book_value' => round((float) $fixedAsset->cost - $cumulative, 2),
                'posted' => $postedMonths->has($label),
            ];

            $cursor->addMonth();
        }

        return $schedule;
    }
}
