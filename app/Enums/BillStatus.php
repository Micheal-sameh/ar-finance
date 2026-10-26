<?php

namespace App\Enums;

enum BillStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Posted to the ledger and still owing something to the vendor.
     */
    public function isPayable(): bool
    {
        return $this === self::Approved || $this === self::PartiallyPaid;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
        };
    }
}
