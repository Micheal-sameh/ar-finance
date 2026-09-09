<?php

namespace App\Exceptions;

use RuntimeException;

class AccountInUseException extends RuntimeException
{
    public static function hasJournalLines(string $code): self
    {
        return new self("Account {$code} cannot be deleted: it has posted journal lines.");
    }

    public static function hasChildren(string $code): self
    {
        return new self("Account {$code} cannot be deleted: it has child accounts.");
    }
}
