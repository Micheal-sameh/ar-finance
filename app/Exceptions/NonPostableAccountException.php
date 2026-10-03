<?php

namespace App\Exceptions;

use RuntimeException;

class NonPostableAccountException extends RuntimeException
{
    public static function hasChildren(string $code): self
    {
        return new self("Account {$code} cannot receive postings: it is a parent account and only acts as a label for its child accounts.");
    }
}
