<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    /**
     * Assets and expenses increase with a debit; liabilities, equity, and
     * revenue increase with a credit.
     */
    public function isDebitNormal(): bool
    {
        return match ($this) {
            self::Asset, self::Expense => true,
            self::Liability, self::Equity, self::Revenue => false,
        };
    }

    public function defaultNormalBalance(): NormalBalance
    {
        return $this->isDebitNormal() ? NormalBalance::Debit : NormalBalance::Credit;
    }

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Asset',
            self::Liability => 'Liability',
            self::Equity => 'Equity',
            self::Revenue => 'Revenue',
            self::Expense => 'Expense',
        };
    }

    /**
     * The chart-of-accounts numbering convention: the digit an account's
     * code must start with for this type (1=Asset, 2=Liability, 3=Equity,
     * 4=Revenue, 5=Expense).
     */
    public function codePrefix(): string
    {
        return match ($this) {
            self::Asset => '1',
            self::Liability => '2',
            self::Equity => '3',
            self::Revenue => '4',
            self::Expense => '5',
        };
    }
}
