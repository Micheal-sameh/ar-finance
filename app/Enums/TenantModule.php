<?php

namespace App\Enums;

/**
 * Optional modules a Platform Admin or Portal Manager can switch on for a
 * tenant. A module that is off is hidden from the tenant's menu and its
 * routes answer 404.
 */
enum TenantModule: string
{
    case Donations = 'donations';

    public function label(): string
    {
        return match ($this) {
            self::Donations => 'Donations',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Donations => 'Record donations from donors, post them to the ledger and print receipts.',
        };
    }
}
