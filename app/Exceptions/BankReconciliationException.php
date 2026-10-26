<?php

namespace App\Exceptions;

use RuntimeException;

class BankReconciliationException extends RuntimeException
{
    public static function unmatchedTransactions(int $count): self
    {
        return new self("{$count} transaction(s) dated on or before the statement date are still unmatched — match or post them first.");
    }

    public static function foreignCurrency(string $currency, string $base): self
    {
        return new self("This bank account is in {$currency} but the ledger is kept in {$base}, so its statement balance can't be compared with the ledger yet.");
    }

    public static function closedPeriod(string $date): self
    {
        return new self("Transactions dated on or before the last reconciled statement ({$date}) can't be imported.");
    }

    public static function notLatest(): self
    {
        return new self('Only the most recent reconciliation of a bank account can be reopened.');
    }

    public static function outOfBalance(string $difference): self
    {
        return new self("The reconciliation is out of balance by {$difference}.");
    }

    public static function dateNotAfterPrevious(string $date): self
    {
        return new self("The statement date must be after the last reconciled statement ({$date}).");
    }
}
