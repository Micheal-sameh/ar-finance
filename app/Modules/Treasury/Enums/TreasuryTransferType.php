<?php

namespace App\Modules\Treasury\Enums;

enum TreasuryTransferType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Funding = 'funding';
    case Repayment = 'repayment';
    case Move = 'move';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Withdrawal => 'Withdrawal',
            self::Funding => 'Funding',
            self::Repayment => 'Repayment',
            self::Move => 'Cash / bank move',
        };
    }

    /**
     * A tenant admin asks for these and the treasurer accepts; the others are
     * recorded by the treasurer and post at once.
     */
    public function needsAcceptance(): bool
    {
        return match ($this) {
            self::Deposit, self::Withdrawal, self::Repayment => true,
            self::Funding, self::Move => false,
        };
    }

    public function touchesTenant(): bool
    {
        return $this !== self::Move;
    }
}
