<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * Purchase orders never touch the ledger themselves — they're a
     * pre-commitment document. Only converting one to a Bill posts
     * anything, and only while the PO is still open for conversion.
     */
    public function isOpenForConversion(): bool
    {
        return $this === self::Draft || $this === self::Sent;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }
}
