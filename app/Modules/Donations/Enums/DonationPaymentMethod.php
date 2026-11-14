<?php

namespace App\Modules\Donations\Enums;

use App\Enums\GlMappingRole;

enum DonationPaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Pos = 'pos';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank transfer',
            self::Pos => 'POS',
        };
    }

    /**
     * The GL mapping role naming the account this method's money lands in.
     */
    public function glRole(): GlMappingRole
    {
        return match ($this) {
            self::Cash => GlMappingRole::DonationCashPayment,
            self::BankTransfer => GlMappingRole::DonationBankPayment,
            self::Pos => GlMappingRole::DonationPosPayment,
        };
    }
}
