<?php

namespace App\Modules\Booking\DTOs;

use App\Modules\Booking\Enums\BookingDiscountType;
use App\Modules\Booking\Enums\BookingPaymentMethod;
use App\Modules\Booking\Enums\BookingRecurrence;

final readonly class CreateBookingData
{
    public function __construct(
        public int $bookableItemId,
        public ?int $bookingTypeId,
        public string $startsAt,
        public string $endsAt,
        public ?string $notes,
        public BookingPaymentMethod $paymentMethod,
        public ?string $paymentReference = null,
        public BookingRecurrence $recurrence = BookingRecurrence::None,
        public ?string $recurrenceUntil = null,
        public ?BookingDiscountType $discountType = null,
        public ?float $discountValue = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            bookableItemId: (int) $data['bookable_item_id'],
            bookingTypeId: isset($data['booking_type_id']) ? (int) $data['booking_type_id'] : null,
            startsAt: $data['starts_at'],
            endsAt: $data['ends_at'],
            notes: $data['notes'] ?? null,
            paymentMethod: BookingPaymentMethod::from($data['payment_method']),
            paymentReference: isset($data['payment_reference']) && trim($data['payment_reference']) !== '' ? trim($data['payment_reference']) : null,
            recurrence: BookingRecurrence::from($data['recurrence'] ?? 'none'),
            recurrenceUntil: $data['recurrence_until'] ?? null,
            discountType: ! empty($data['discount_type']) ? BookingDiscountType::from($data['discount_type']) : null,
            discountValue: isset($data['discount_value']) && $data['discount_value'] !== '' ? (float) $data['discount_value'] : null,
        );
    }
}
