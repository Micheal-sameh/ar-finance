<?php

namespace App\Exceptions;

use RuntimeException;

class AccountImportException extends RuntimeException
{
    /**
     * @param  string[]  $rowErrors
     */
    public function __construct(private readonly array $rowErrors)
    {
        parent::__construct('The account import could not be completed.');
    }

    /**
     * @return string[]
     */
    public function rowErrors(): array
    {
        return $this->rowErrors;
    }
}
