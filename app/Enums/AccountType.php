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
}
