<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\TenantContext;

/**
 * Side-by-side tenant comparison for the top-line totals of each existing
 * report — each tenant is a separate legal entity with its own books, so
 * "combined" here means comparing entities, not merging their statements
 * line-by-line (see ReportService, which stays untouched). Every method
 * loops active tenants under TenantContext::runAs() and reuses the real
 * report calculation for that tenant.
 */
class PlatformReportService
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array<int, array{tenant_id: int, tenant_name: string, base_currency: string, total_debit: float, total_credit: float, is_balanced: bool}>
     */
    public function trialBalance(?string $from, ?string $to): array
    {
        return $this->byTenant(fn (Tenant $tenant) => $this->tenantContext->runAs($tenant->id, function () use ($from, $to) {
            $report = $this->reports->trialBalance($from, $to);

            return [
                'total_debit' => $report['total_closing_debit'],
                'total_credit' => $report['total_closing_credit'],
                'is_balanced' => $report['is_balanced'],
            ];
        }));
    }

    /**
     * @return array<int, array{tenant_id: int, tenant_name: string, base_currency: string, total_revenue: float, total_expenses: float, net_profit: float}>
     */
    public function profitAndLoss(string $from, string $to): array
    {
        return $this->byTenant(fn (Tenant $tenant) => $this->tenantContext->runAs($tenant->id, function () use ($from, $to) {
            $report = $this->reports->profitAndLoss($from, $to);

            return [
                'total_revenue' => $report['total_revenue']['current'],
                'total_expenses' => $report['total_expenses']['current'],
                'net_profit' => $report['net_profit']['current'],
            ];
        }));
    }

    /**
     * @return array<int, array{tenant_id: int, tenant_name: string, base_currency: string, total_assets: float, total_liabilities: float, total_equity: float, is_balanced: bool}>
     */
    public function balanceSheet(string $asOf): array
    {
        return $this->byTenant(fn (Tenant $tenant) => $this->tenantContext->runAs($tenant->id, function () use ($asOf) {
            $report = $this->reports->balanceSheet($asOf);

            return [
                'total_assets' => $report['total_assets'],
                'total_liabilities' => $report['total_liabilities'],
                'total_equity' => $report['total_equity'],
                'is_balanced' => $report['is_balanced'],
            ];
        }));
    }

    /**
     * @return array<int, array{tenant_id: int, tenant_name: string, base_currency: string, beginning_cash: float, ending_cash: float, net_change_in_cash: float}>
     */
    public function cashFlow(string $from, string $to): array
    {
        return $this->byTenant(fn (Tenant $tenant) => $this->tenantContext->runAs($tenant->id, function () use ($from, $to) {
            $report = $this->reports->cashFlow($from, $to);

            return [
                'beginning_cash' => $report['beginning_cash'],
                'ending_cash' => $report['ending_cash'],
                'net_change_in_cash' => $report['net_change_in_cash'],
            ];
        }));
    }

    /**
     * @return array<int, array{tenant_id: int, tenant_name: string, base_currency: string, output_vat: float, input_vat: float, net_vat_payable: float}>
     */
    public function vatReturn(string $from, string $to): array
    {
        return $this->byTenant(fn (Tenant $tenant) => $this->tenantContext->runAs($tenant->id, function () use ($from, $to) {
            $report = $this->reports->vatReturn($from, $to);

            return [
                'output_vat' => $report['output_vat'],
                'input_vat' => $report['input_vat'],
                'net_vat_payable' => $report['net_vat_payable'],
            ];
        }));
    }

    /**
     * @return array<int, array{tenant_id: int, tenant_name: string, base_currency: string, total_ar: float, total_ap: float}>
     */
    public function aging(string $asOf): array
    {
        return $this->byTenant(fn (Tenant $tenant) => $this->tenantContext->runAs($tenant->id, function () use ($asOf) {
            return [
                'total_ar' => $this->reports->arAging($asOf)['totals']['total'],
                'total_ap' => $this->reports->apAging($asOf)['totals']['total'],
            ];
        }));
    }

    /**
     * @param  callable(Tenant): array<string, mixed>  $forTenant
     * @return array<int, array<string, mixed>>
     */
    private function byTenant(callable $forTenant): array
    {
        return Tenant::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (Tenant $tenant) => array_merge([
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'base_currency' => $tenant->base_currency,
            ], $forTenant($tenant)))
            ->values()
            ->all();
    }
}
