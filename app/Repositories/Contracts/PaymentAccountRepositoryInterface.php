<?php

namespace App\Repositories\Contracts;

use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Collection;

interface PaymentAccountRepositoryInterface
{
    /**
     * @return Collection<int, PaymentAccount>
     */
    public function all(): Collection;

    /**
     * Replaces the tenant's whole set of extra accounts.
     *
     * @param  array<int, array{method: string, account_id: int, label: ?string}>  $rows
     */
    public function replaceAll(array $rows): void;
}
