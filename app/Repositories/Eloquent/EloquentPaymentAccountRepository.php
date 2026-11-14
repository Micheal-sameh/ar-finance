<?php

namespace App\Repositories\Eloquent;

use App\Models\PaymentAccount;
use App\Repositories\Contracts\PaymentAccountRepositoryInterface;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;

class EloquentPaymentAccountRepository implements PaymentAccountRepositoryInterface
{
    public function all(): Collection
    {
        return PaymentAccount::query()->with('account:id,code,name')->orderBy('id')->get();
    }

    public function replaceAll(array $rows): void
    {
        PaymentAccount::query()->get()->each->delete();

        foreach ($rows as $row) {
            PaymentAccount::create([
                'tenant_id' => app(TenantContext::class)->id(),
                'method' => $row['method'],
                'account_id' => $row['account_id'],
                'label' => $row['label'],
            ]);
        }
    }
}
