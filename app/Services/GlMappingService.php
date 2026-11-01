<?php

namespace App\Services;

use App\Enums\GlMappingRole;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Contracts\GlMappingRepositoryInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GlMappingService
{
    public function __construct(
        private readonly GlMappingRepositoryInterface $mappings,
        private readonly AccountRepositoryInterface $accounts,
    ) {}

    /**
     * The mapped account id for every role, null where none is set. Forms
     * use this to prefill their account pickers.
     *
     * @return array<string, int|null>
     */
    public function defaults(): array
    {
        $mapped = $this->mappings->accountIdsByRole();

        $defaults = [];
        foreach (GlMappingRole::cases() as $role) {
            $defaults[$role->value] = $mapped->get($role->value);
        }

        return $defaults;
    }

    /**
     * One row per role for the mapping screen.
     *
     * @return array<int, array{role: string, label: string, description: string, group: string, account_id: int|null, allowed_types: array<int, string>}>
     */
    public function rows(): array
    {
        $defaults = $this->defaults();

        return array_map(fn (GlMappingRole $role) => [
            'role' => $role->value,
            'label' => $role->label(),
            'description' => $role->description(),
            'group' => $role->group(),
            'account_id' => $defaults[$role->value],
            'allowed_types' => array_map(fn ($type) => $type->value, $role->allowedTypes()),
        ], GlMappingRole::cases());
    }

    /**
     * Saves the whole mapping at once: a role with an account is set, one
     * with null is cleared. Nothing is saved if any account is unsuitable.
     *
     * @param  array<string, int|null>  $mappings  Account id by role value.
     *
     * @throws RuntimeException
     */
    public function save(array $mappings): void
    {
        $resolved = [];
        foreach ($mappings as $roleValue => $accountId) {
            $role = GlMappingRole::tryFrom((string) $roleValue)
                ?? throw new RuntimeException("Unknown mapping '{$roleValue}'.");

            if ($accountId !== null) {
                $this->assertSuitable($role, $accountId);
            }

            $resolved[] = [$role, $accountId];
        }

        DB::transaction(function () use ($resolved) {
            foreach ($resolved as [$role, $accountId]) {
                $accountId === null ? $this->mappings->clear($role) : $this->mappings->set($role, $accountId);
            }
        });
    }

    private function assertSuitable(GlMappingRole $role, int $accountId): void
    {
        $account = $this->accounts->find($accountId)
            ?? throw new RuntimeException("The account chosen for {$role->label()} does not exist.");

        if (! $account->is_active) {
            throw new RuntimeException("{$account->code} {$account->name} is inactive, so it cannot be used for {$role->label()}.");
        }

        if (in_array($account->type, $role->allowedTypes(), true) === false) {
            $allowed = implode(' or ', array_map(fn ($type) => strtolower($type->label()), $role->allowedTypes()));

            throw new RuntimeException("{$role->label()} needs a {$allowed} account, but {$account->code} {$account->name} is {$account->type->label()}.");
        }

        if ($account->children()->exists()) {
            throw new RuntimeException("{$account->code} {$account->name} is a parent account, so it cannot be posted to. Choose one of its sub-accounts for {$role->label()}.");
        }
    }
}
