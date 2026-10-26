<?php

namespace App\Repositories\Eloquent;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Repositories\Contracts\BudgetRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentBudgetRepository implements BudgetRepositoryInterface
{
    public function all(): Collection
    {
        return Budget::query()->with('lines.account:id,type')->orderByDesc('year')->get();
    }

    public function findForYear(int $year): ?Budget
    {
        return Budget::query()->where('year', $year)->first();
    }

    public function linesForYears(array $years): Collection
    {
        return BudgetLine::query()
            ->with(['account:id,code,name,type', 'costCenter:id,name', 'budget:id,year'])
            ->whereHas('budget', fn ($query) => $query->whereIn('year', $years))
            ->get();
    }

    public function replaceLines(int $year, int $tenantId, array $lines): Budget
    {
        return DB::transaction(function () use ($year, $tenantId, $lines) {
            $budget = Budget::query()->firstOrCreate(['year' => $year], ['tenant_id' => $tenantId]);

            $budget->lines()->delete();

            foreach ($lines as $line) {
                $attributes = [
                    'tenant_id' => $budget->tenant_id,
                    'account_id' => $line['account_id'],
                    'cost_center_id' => $line['cost_center_id'],
                ];

                foreach ($line['months'] as $month => $amount) {
                    $attributes["m{$month}"] = $amount;
                }

                $budget->lines()->create($attributes);
            }

            $budget->touch();

            return $budget;
        });
    }

    public function delete(Budget $budget): void
    {
        $budget->delete();
    }
}
