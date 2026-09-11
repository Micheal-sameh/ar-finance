<?php

namespace App\Exceptions;

use RuntimeException;

class BankTransactionMatchException extends RuntimeException
{
    public static function amountMismatch(): self
    {
        return new self('That journal line does not match this transaction\'s amount.');
    }

    public static function wrongAccount(): self
    {
        return new self('That journal line is not posted to this bank account\'s ledger account.');
    }

    public static function alreadyMatched(): self
    {
        return new self('This transaction is already matched — unmatch it first.');
    }
}
