<?php

namespace App\Modules\Booking\DTOs;

use App\Modules\Booking\Enums\BookingRecurrence;

final readonly class CreateBookingData
{
    public function __construct(
        public int $bookableItemId,
        public ?int $bookingTypeId,
        public string $startsAt,
        public string $endsAt,
        public ?string $notes,
        public ?string $paymentDueAt,
        public ?float $depositAmount,
        public BookingRecurrence $recurrence = BookingRecurrence::None,
        public ?string $recurrenceUntil = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            bookableItemId: (int) $data['bookable_item_id'],
            bookingTypeId: isset($data['booking_type_id']) ? (int) $data['booking_type_id'] : null,
            startsAt: $data['starts_at'],
            endsAt: $data['ends_at'],
            notes: $data['notes'] ?? null,
            paymentDueAt: $data['payment_due_at'] ?? null,
            recurrence: BookingRecurrence::from($data['recurrence'] ?? 'none'),
            recurrenceUntil: $data['recurrence_until'] ?? null,
            depositAmount: isset($data['deposit_amount']) && $data['deposit_amount'] !== '' ? (float) $data['deposit_amount'] : null,
        );
    }
}
