<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Collection;

/**
 * Combines the existing per-tenant DashboardService::summary() across every
 * active tenant — run once per tenant under TenantContext::runAs() rather
 * than reimplementing the KPI math, so this stays correct as the
 * single-tenant dashboard evolves.
 */
class PlatformDashboardService
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array{
     *     totals: array{cash: float, receivables: float, payables: float, revenue_month: float, expenses_month: float, net_profit_month: float},
     *     trend: array<int, array{month: string, revenue: float, expenses: float}>,
     *     by_tenant: array<int, array{tenant_id: int, tenant_name: string, base_currency: string, cash: float, receivables: float, payables: float, revenue_month: float, expenses_month: float, net_profit_month: float}>,
     *     recent_activity: array<int, array<string, mixed>>,
     *     mixed_currencies: bool,
     * }
     */
    public function summary(): array
    {
        $tenants = Tenant::query()->where('is_active', true)->orderBy('name')->get();
        $mixedCurrencies = $tenants->pluck('base_currency')->unique()->count() > 1;

        $perTenant = $tenants->map(fn (Tenant $tenant) => [
            'tenant' => $tenant,
            'summary' => $this->tenantContext->runAs($tenant->id, fn () => $this->dashboard->summary()),
        ]);

        $totals = [
            'cash' => round($perTenant->sum(fn ($row) => $row['summary']['cash']), 2),
            'receivables' => round($perTenant->sum(fn ($row) => $row['summary']['receivables']), 2),
            'payables' => round($perTenant->sum(fn ($row) => $row['summary']['payables']), 2),
            'revenue_month' => round($perTenant->sum(fn ($row) => $row['summary']['revenue_month']), 2),
            'expenses_month' => round($perTenant->sum(fn ($row) => $row['summary']['expenses_month']), 2),
            'net_profit_month' => round($perTenant->sum(fn ($row) => $row['summary']['net_profit_month']), 2),
        ];

        $byTenant = $perTenant->map(fn ($row) => [
            'tenant_id' => $row['tenant']->id,
            'tenant_name' => $row['tenant']->name,
            'base_currency' => $row['tenant']->base_currency,
            'cash' => $row['summary']['cash'],
            'receivables' => $row['summary']['receivables'],
            'payables' => $row['summary']['payables'],
            'revenue_month' => $row['summary']['revenue_month'],
            'expenses_month' => $row['summary']['expenses_month'],
            'net_profit_month' => $row['summary']['net_profit_month'],
        ])->values()->all();

        $trend = $this->mergeTrend($perTenant);

        $recentActivity = $perTenant
            ->flatMap(fn ($row) => collect($row['summary']['recent_activity'])->map(
                fn (array $activity) => $activity + ['tenant_name' => $row['tenant']->name],
            ))
            ->sortByDesc('date')
            ->take(8)
            ->values()
            ->all();

        return [
            'totals' => $totals,
            'trend' => $trend,
            'by_tenant' => $byTenant,
            'recent_activity' => $recentActivity,
            'mixed_currencies' => $mixedCurrencies,
        ];
    }

    /**
     * @param  Collection<int, array{tenant: Tenant, summary: array<string, mixed>}>  $perTenant
     * @return array<int, array{month: string, revenue: float, expenses: float}>
     */
    private function mergeTrend(Collection $perTenant): array
    {
        $byMonth = [];

        foreach ($perTenant as $row) {
            foreach ($row['summary']['trend'] as $point) {
                $byMonth[$point['month']] ??= ['month' => $point['month'], 'revenue' => 0.0, 'expenses' => 0.0];
                $byMonth[$point['month']]['revenue'] += $point['revenue'];
                $byMonth[$point['month']]['expenses'] += $point['expenses'];
            }
        }

        return array_values($byMonth);
    }
}
