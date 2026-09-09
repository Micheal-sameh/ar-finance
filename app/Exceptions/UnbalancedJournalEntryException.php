<?php

namespace App\Exceptions;

use RuntimeException;

class UnbalancedJournalEntryException extends RuntimeException
{
    public static function forTotals(float $totalDebit, float $totalCredit): self
    {
        return new self(sprintf(
            'Journal entry is not balanced: debits %.2f do not equal credits %.2f.',
            $totalDebit,
            $totalCredit,
        ));
    }
}
