<?php

namespace App\Modules\Treasury\Enums;

enum TreasuryTransferStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Voided => 'Voided',
        };
    }

    public function isDecidable(): bool
    {
        return $this === self::Requested;
    }

    public function isVoidable(): bool
    {
        return $this === self::Accepted;
    }
}
