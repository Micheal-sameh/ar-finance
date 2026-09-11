<?php

namespace App\Exceptions;

use RuntimeException;

class DepreciationAlreadyPostedException extends RuntimeException
{
    public static function forMonth(string $assetName, string $month): self
    {
        return new self("Depreciation for \"{$assetName}\" has already been posted for {$month}.");
    }
}
