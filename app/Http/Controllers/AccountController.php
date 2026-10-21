<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountImportException;
use App\Exceptions\AccountInUseException;
use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\Accounts\ImportAccountsRequest;
use App\Http\Requests\Accounts\StoreAccountRequest;
use App\Http\Requests\Accounts\UpdateAccountRequest;
use App\Http\Resources\AccountOptionResource;
use App\Models\Account;
use App\Services\AccountService;
use App\Services\ExchangeRateService;
use App\Services\ReportService;
use App\Support\Excel\AccountImportTemplate;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    use ExportsExcel;

    public function __construct(
        private readonly AccountService $accounts,
        private readonly ReportService $reports,
        private readonly ExchangeRateService $exchangeRates,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $viewingAllTenants = $this->tenantContext->isViewingAllTenants();
        $balances = $this->reports->accountBalances();
        $filtered = $this->accounts->filtered($request->only(['type', 'is_active', 'search']));

        $accounts = $viewingAllTenants
            ? $this->mergeAccountsAcrossTenants($filtered, $balances)
            : $filtered->map(fn (Account $account) => [
                ...$account->toArray(),
                'balance' => $balances[$account->id] ?? 0.0,
                'tenant_name' => null,
            ]);

        return Inertia::render('Accounting/Accounts/Index', [
            'accounts' => $accounts,
            'filters' => $request->only(['type', 'is_active', 'search']),
            'baseCurrency' => $this->exchangeRates->baseCurrency(),
            'currencyOptions' => $this->exchangeRates->currencyOptions(),
            'viewingAllTenants' => $viewingAllTenants,
        ]);
    }

    /**
     * Collapses one row per account *code* across every tenant — every
     * tenant starts from the same seeded chart of accounts (see
     * ChartOfAccountsSeeder), so "1000 Assets" is the same conceptual
     * account everywhere, just a separate row per tenant. The merged
     * row's balance is the sum across tenants; each tenant's own account
     * (id, balance, status) rides along in `tenants` for the UI to expand
     * on click rather than guessing from the aggregate.
     *
     * @param  Collection<int, Account>  $accounts
     * @param  array<int, float>  $balances
     * @return array<int, array<string, mixed>>
     */
    private function mergeAccountsAcrossTenants(Collection $accounts, array $balances): array
    {
        $codeById = $accounts->pluck('code', 'id');

        return $accounts->groupBy('code')
            ->map(function ($group) use ($balances, $codeById) {
                $first = $group->first();

                return [
                    'code' => $first->code,
                    'name' => $first->name,
                    'type' => $first->type->value,
                    'normal_balance' => $first->normal_balance->value,
                    'currency' => $first->currency,
                    'parent_code' => $first->parent_id ? ($codeById[$first->parent_id] ?? null) : null,
                    'is_active' => $group->contains('is_active', true),
                    'is_deletable' => false,
                    'balance' => round($group->sum(fn (Account $account) => $balances[$account->id] ?? 0.0), 2),
                    'tenants' => $group->map(fn (Account $account) => [
                        'tenant_id' => $account->tenant_id,
                        'tenant_name' => $account->tenant?->name,
                        'account_id' => $account->id,
                        'balance' => $balances[$account->id] ?? 0.0,
                        'is_active' => $account->is_active,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Lightweight list for the AccountPicker combobox — id/code/name/type
     * only, never a full Eloquent-shaped payload.
     */
    public function options(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Account::class);

        return AccountOptionResource::collection($this->accounts->all());
    }

    public function show(Account $account): Response
    {
        $this->authorize('view', $account);

        return Inertia::render('Accounting/Accounts/Show', [
            'account' => $this->accounts->find($account->id),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $this->accounts->create($request->toDto(), $request->user()->id);

        return redirect()->route('accounts.index')->with('success', 'Account created.');
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->accounts->update($account, $request->toDto());

        return redirect()->route('accounts.index')->with('success', 'Account updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Account::class);

        $balances = $this->reports->accountBalances();
        $accounts = $this->accounts->filtered($request->only(['type', 'is_active', 'search']));

        $rows = $accounts->map(fn (Account $account) => [
            $account->code,
            $account->name,
            $account->type->value,
            $account->normal_balance->value,
            $account->currency,
            $balances[$account->id] ?? 0.0,
            $account->is_active ? 'Active' : 'Inactive',
        ]);

        return $this->exportXlsx('accounts.xlsx', ['Code', 'Name', 'Type', 'Normal Balance', 'Currency', 'Balance', 'Status'], $rows);
    }

    /**
     * Starter workbook for the accounts import — built by
     * AccountImportTemplate so the controller stays a thin entry point.
     */
    public function importTemplate(): StreamedResponse
    {
        $this->authorize('create', Account::class);

        return AccountImportTemplate::download();
    }

    public function import(ImportAccountsRequest $request): RedirectResponse
    {
        try {
            $count = $this->accounts->import($request->rows(), $request->user()->id);
        } catch (AccountImportException $e) {
            return back()->withErrors(['file' => implode("\n", $e->rowErrors())]);
        }

        return redirect()->route('accounts.index')->with('success', "Imported {$count} account(s).");
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        try {
            $this->accounts->delete($account);
        } catch (AccountInUseException $e) {
            return redirect()->route('accounts.index')->with('error', $e->getMessage());
        }

        return redirect()->route('accounts.index')->with('success', 'Account deleted.');
    }
}
