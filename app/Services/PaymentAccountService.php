<?php

namespace App\Services;

use App\Enums\GlMappingRole;
use App\Models\PaymentAccount;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Contracts\PaymentAccountRepositoryInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bank transfer, InstaPay and POS can each be paid into several ledger
 * accounts. The account in the GL mapping for the module's role is the
 * default; the extras configured here are the other choices, shared by
 * Booking and Donations. Cash always uses its single mapped account.
 */
class PaymentAccountService
{
    /** Methods that can have more than one account. */
    public const METHODS = ['bank_transfer', 'instapay', 'pos'];

    public function __construct(
        private readonly PaymentAccountRepositoryInterface $paymentAccounts,
        private readonly AccountRepositoryInterface $accounts,
        private readonly GlMappingService $glMapping,
    ) {}

    /**
     * The extras for the configuration screen.
     *
     * @return array<int, array{method: string, account_id: int, label: string|null}>
     */
    public function configured(): array
    {
        return $this->paymentAccounts->all()
            ->map(fn (PaymentAccount $row) => ['method' => $row->method, 'account_id' => $row->account_id, 'label' => $row->label])
            ->values()
            ->all();
    }

    /**
     * Replaces the extras. Each account must be an active, postable asset.
     *
     * @param  array<int, array{method: string, account_id: int, label: string|null}>  $rows
     *
     * @throws RuntimeException
     */
    public function save(array $rows): void
    {
        $seen = [];

        foreach ($rows as $row) {
            if (! in_array($row['method'], self::METHODS, true)) {
                throw new RuntimeException("Unknown payment method '{$row['method']}'.");
            }

            $key = $row['method'].'|'.$row['account_id'];

            if (isset($seen[$key])) {
                throw new RuntimeException('The same account is listed twice for one payment method.');
            }

            $seen[$key] = true;
            $this->assertSuitable($row['account_id']);
        }

        DB::transaction(fn () => $this->paymentAccounts->replaceAll($rows));
    }

    /**
     * Every account a payment of this method can go to: the mapped default
     * first, then the extras. Cash has just the default.
     *
     * @return array<int, array{id: int, label: string}>
     */
    public function optionsFor(string $method, GlMappingRole $defaultRole): array
    {
        $options = [];
        $defaultId = $this->glMapping->defaults()[$defaultRole->value] ?? null;

        if ($defaultId !== null) {
            $options[$defaultId] = ['id' => $defaultId, 'label' => $this->accountLabel($defaultId, null)];
        }

        if (in_array($method, self::METHODS, true)) {
            foreach ($this->paymentAccounts->all() as $row) {
                if ($row->method === $method && ! isset($options[$row->account_id])) {
                    $options[$row->account_id] = ['id' => $row->account_id, 'label' => $this->accountLabel($row->account_id, $row->label)];
                }
            }
        }

        return $this->disambiguated(array_values($options));
    }

    /**
     * Two options can share a name (e.g. two accounts both called "Bank");
     * only then is the account code added so they can be told apart.
     *
     * @param  array<int, array{id: int, label: string}>  $options
     * @return array<int, array{id: int, label: string}>
     */
    private function disambiguated(array $options): array
    {
        $counts = array_count_values(array_column($options, 'label'));

        return array_map(function (array $option) use ($counts) {
            if ($counts[$option['label']] > 1) {
                $code = $this->accounts->find($option['id'])?->code;
                $option['label'] .= $code !== null ? " ({$code})" : '';
            }

            return $option;
        }, $options);
    }

    /**
     * Picks the ledger account for a payment: the one the user chose when
     * it is among the options, otherwise the default.
     *
     * @throws RuntimeException
     */
    public function resolve(string $method, GlMappingRole $defaultRole, ?int $chosenId, string $methodLabel): int
    {
        $options = $this->optionsFor($method, $defaultRole);

        if ($chosenId !== null) {
            foreach ($options as $option) {
                if ($option['id'] === $chosenId) {
                    return $chosenId;
                }
            }

            throw new RuntimeException("That account is not set up for {$methodLabel} payments.");
        }

        if ($options === []) {
            throw new RuntimeException("No account is set for {$methodLabel} payments — ask finance or an admin to set \"{$defaultRole->label()}\" in the General Ledger mapping, or choose another payment method.");
        }

        return $options[0]['id'];
    }

    /**
     * Every method's options for a module's form, keyed by method value.
     *
     * @param  array<string, GlMappingRole>  $rolesByMethod
     * @return array<string, array<int, array{id: int, label: string}>>
     */
    public function optionsByMethod(array $rolesByMethod): array
    {
        $result = [];

        foreach ($rolesByMethod as $method => $role) {
            $result[$method] = $this->optionsFor($method, $role);
        }

        return $result;
    }

    private function accountLabel(int $accountId, ?string $label): string
    {
        if ($label !== null && $label !== '') {
            return $label;
        }

        return $this->accounts->find($accountId)?->name ?? "#{$accountId}";
    }

    private function assertSuitable(int $accountId): void
    {
        $account = $this->accounts->find($accountId)
            ?? throw new RuntimeException('One of the chosen accounts does not exist.');

        if (! $account->is_active) {
            throw new RuntimeException("{$account->code} {$account->name} is inactive, so it cannot receive payments.");
        }

        if ($account->type->value !== 'asset') {
            throw new RuntimeException("{$account->code} {$account->name} must be an asset account (bank or cash).");
        }

        if ($account->children()->exists()) {
            throw new RuntimeException("{$account->code} {$account->name} is a parent account, so it cannot be posted to.");
        }
    }
}
