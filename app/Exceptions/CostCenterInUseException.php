<?php

namespace App\Exceptions;

use RuntimeException;

class CostCenterInUseException extends RuntimeException
{
    public static function hasActivity(string $name): self
    {
        return new self("Cost center \"{$name}\" cannot be deleted: it has tagged journal lines or expenses.");
    }

    public static function hasChildren(string $name): self
    {
        return new self("Cost center \"{$name}\" cannot be deleted: it has child cost centers.");
    }
}
