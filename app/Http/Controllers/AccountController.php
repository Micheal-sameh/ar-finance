<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountInUseException;
use App\Http\Requests\Accounts\StoreAccountRequest;
use App\Http\Requests\Accounts\UpdateAccountRequest;
use App\Http\Resources\AccountOptionResource;
use App\Models\Account;
use App\Services\AccountService;
use App\Services\ExchangeRateService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly ReportService $reports,
        private readonly ExchangeRateService $exchangeRates,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $balances = $this->reports->accountBalances();
        $accounts = $this->accounts->filtered($request->only(['type', 'is_active', 'search']))
            ->map(fn (Account $account) => [
                ...$account->toArray(),
                'balance' => $balances[$account->id] ?? 0.0,
            ]);

        return Inertia::render('Accounting/Accounts/Index', [
            'accounts' => $accounts,
            'filters' => $request->only(['type', 'is_active', 'search']),
            'baseCurrency' => $this->exchangeRates->baseCurrency(),
        ]);
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
