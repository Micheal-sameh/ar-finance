<?php

namespace App\Repositories\Contracts;

use App\Models\Budget;
use App\Models\BudgetLine;
use Illuminate\Support\Collection;

interface BudgetRepositoryInterface
{
    /**
     * @return Collection<int, Budget> Newest year first, with lines loaded.
     */
    public function all(): Collection;

    public function findForYear(int $year): ?Budget;

    /**
     * Every line of the budgets for the given years, with its account and cost center.
     *
     * @param  array<int, int>  $years
     * @return Collection<int, BudgetLine>
     */
    public function linesForYears(array $years): Collection;

    /**
     * Replaces the whole set of lines of a year's budget (creating the budget if needed).
     *
     * @param  array<int, array{account_id: int, cost_center_id: ?int, months: array<int, float>}>  $lines
     */
    public function replaceLines(int $year, int $tenantId, array $lines): Budget;

    public function delete(Budget $budget): void;
}
