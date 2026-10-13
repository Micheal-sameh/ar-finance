<?php

namespace App\Modules\Booking\DTOs;

final readonly class CreateBookingData
{
    public function __construct(
        public int $bookableItemId,
        public string $startsAt,
        public string $endsAt,
        public ?string $notes,
        public ?string $paymentDueAt,
        public ?float $depositAmount,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            bookableItemId: (int) $data['bookable_item_id'],
            startsAt: $data['starts_at'],
            endsAt: $data['ends_at'],
            notes: $data['notes'] ?? null,
            paymentDueAt: $data['payment_due_at'] ?? null,
            depositAmount: isset($data['deposit_amount']) && $data['deposit_amount'] !== '' ? (float) $data['deposit_amount'] : null,
        );
    }
}
