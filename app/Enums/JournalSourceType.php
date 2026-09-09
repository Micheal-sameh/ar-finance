<?php

namespace App\Enums;

enum JournalSourceType: string
{
    case Invoice = 'invoice';
    case Expense = 'expense';
    case Payroll = 'payroll';
    case Manual = 'manual';
    case Depreciation = 'depreciation';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Invoice',
            self::Expense => 'Expense',
            self::Payroll => 'Payroll',
            self::Manual => 'Manual',
            self::Depreciation => 'Depreciation',
        };
    }
}
