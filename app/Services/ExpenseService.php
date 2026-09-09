<?php

namespace App\Services;

use App\DTOs\CreateExpenseData;
use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\JournalSourceType;
use App\Models\Expense;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Approve-then-pay mirrors InvoiceService's send-then-pay: recognizing an
 * expense (Dr Expense / Cr Accounts Payable) and settling it (Dr Accounts
 * Payable / Cr Cash) are two separate ledger events, not one.
 */
class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
        private readonly JournalService $journals,
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->expenses->paginate($filters, $perPage);
    }

    public function find(int $id): ?Expense
    {
        return $this->expenses->find($id);
    }

    /**
     * Recorded but unapproved — no GL post yet.
     */
    public function create(CreateExpenseData $data): Expense
    {
        return $this->expenses->create([
            'description' => $data->description,
            'account_id' => $data->accountId,
            'amount' => $data->amount,
            'date' => $data->date,
            'vendor_id' => $data->vendorId,
            'cost_center_id' => $data->costCenterId,
            'payable_account_id' => $data->payableAccountId,
            'status' => 'pending',
        ]);
    }

    public function approve(Expense $expense): Expense
    {
        if ($expense->status->value !== 'pending') {
            throw new RuntimeException("Expense \"{$expense->description}\" has already been approved.");
        }

        return DB::transaction(function () use ($expense) {
            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: $expense->date->toDateString(),
                description: $expense->description,
                reference: null,
                sourceType: JournalSourceType::Expense,
                sourceId: $expense->id,
                createdBy: auth()->id(),
                lines: [
                    new JournalLineData(accountId: $expense->account_id, debit: (float) $expense->amount, credit: 0, costCenterId: $expense->cost_center_id),
                    new JournalLineData(accountId: $expense->payable_account_id, debit: 0, credit: (float) $expense->amount),
                ],
            ));

            return $this->expenses->updateStatus($expense, 'approved');
        });
    }

    public function markPaid(Expense $expense, int $paymentAccountId): Expense
    {
        if ($expense->status->value !== 'approved') {
            throw new RuntimeException("Expense \"{$expense->description}\" is not awaiting payment.");
        }

        return DB::transaction(function () use ($expense, $paymentAccountId) {
            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: now()->toDateString(),
                description: "Payment for \"{$expense->description}\"",
                reference: null,
                sourceType: JournalSourceType::Expense,
                sourceId: $expense->id,
                createdBy: auth()->id(),
                lines: [
                    new JournalLineData(accountId: $expense->payable_account_id, debit: (float) $expense->amount, credit: 0),
                    new JournalLineData(accountId: $paymentAccountId, debit: 0, credit: (float) $expense->amount),
                ],
            ));

            return $this->expenses->updateStatus($expense, 'paid', now());
        });
    }
}
