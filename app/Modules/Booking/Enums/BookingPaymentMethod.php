<?php

namespace App\Modules\Booking\Enums;

use App\Enums\GlMappingRole;

enum BookingPaymentMethod: string
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
            self::Cash => GlMappingRole::BookingCashPayment,
            self::BankTransfer => GlMappingRole::BookingBankPayment,
            self::Pos => GlMappingRole::BookingPosPayment,
        };
    }
}
