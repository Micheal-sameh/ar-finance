<?php

namespace App\Exceptions;

use RuntimeException;

class BankReconciliationException extends RuntimeException
{
    public static function unmatchedTransactions(int $count): self
    {
        return new self("{$count} transaction(s) dated on or before the statement date are still unmatched — match or post them first.");
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
