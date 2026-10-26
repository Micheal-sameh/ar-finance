<?php

namespace App\Services;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\JournalSourceType;
use App\Models\ReceivedPayment;
use App\Repositories\Contracts\ReceivedPaymentRepositoryInterface;
use App\Support\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Receiving money with no invoice behind it: Dr deposit account (bank/cash)
 * / Cr the account the money is for, posted straight away.
 */
class ReceivedPaymentService
{
    public function __construct(
        private readonly ReceivedPaymentRepositoryInterface $receivedPayments,
        private readonly JournalService $journals,
    ) {}

    /**
     * @param  array{from?: string, to?: string, search?: string}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->receivedPayments->paginate($filters, $perPage);
    }

    public function find(int $id): ?ReceivedPayment
    {
        return $this->receivedPayments->find($id);
    }

    /**
     * @param  array{description: string, amount: numeric-string|float|int, received_on: string, deposit_account_id: int, credit_account_id: int, client_id?: int|null, reference?: string|null}  $data
     */
    public function record(array $data): ReceivedPayment
    {
        return DB::transaction(function () use ($data) {
            $amount = round((float) $data['amount'], 2);

            $entry = $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: $data['received_on'],
                description: $data['description'],
                reference: $data['reference'] ?? null,
                sourceType: JournalSourceType::ReceivedPayment,
                sourceId: null,
                createdBy: auth()->id(),
                lines: [
                    new JournalLineData(accountId: (int) $data['deposit_account_id'], debit: $amount, credit: 0),
                    new JournalLineData(accountId: (int) $data['credit_account_id'], debit: 0, credit: $amount),
                ],
            ));

            return $this->receivedPayments->create([
                'tenant_id' => app(TenantContext::class)->id(),
                'client_id' => $data['client_id'] ?? null,
                'description' => $data['description'],
                'amount' => $amount,
                'received_on' => $data['received_on'],
                'deposit_account_id' => $data['deposit_account_id'],
                'credit_account_id' => $data['credit_account_id'],
                'reference' => $data['reference'] ?? null,
                'journal_entry_id' => $entry->id,
                'created_by' => auth()->id(),
            ]);
        });
    }
}
