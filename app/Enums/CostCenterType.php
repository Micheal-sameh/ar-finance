<?php

namespace App\Enums;

enum CostCenterType: string
{
    case Cost = 'cost';
    case Profit = 'profit';

    public function label(): string
    {
        return match ($this) {
            self::Cost => 'Cost Center',
            self::Profit => 'Profit Center',
        };
    }
}
