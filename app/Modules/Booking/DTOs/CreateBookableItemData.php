<?php

namespace App\Modules\Booking\DTOs;

use App\Modules\Booking\Enums\BookingRateType;

/**
 * Account and cost-center fields are only chosen by finance/admin
 * ($includesAccounting); when false they must be left untouched on update
 * and defaulted from the GL mapping on create.
 */
final readonly class CreateBookableItemData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public bool $isExclusive,
        public ?int $accountId,
        public ?int $costCenterId,
        /** @var array<int, array{booking_type_id: int, rate_type: string, price: float, account_id: int|null, cost_center_id: int|null}> */
        public array $bookingTypes = [],
        public bool $isActive = true,
        public bool $includesAccounting = true,
    ) {}

    public static function fromArray(array $data, bool $includesAccounting = true): self
    {
        $nullableInt = fn (mixed $value): ?int => isset($value) && $value !== '' ? (int) $value : null;

        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            isExclusive: (bool) ($data['is_exclusive'] ?? true),
            accountId: $nullableInt($data['account_id'] ?? null),
            costCenterId: $nullableInt($data['cost_center_id'] ?? null),
            bookingTypes: array_map(fn (array $type) => [
                'booking_type_id' => (int) $type['booking_type_id'],
                'rate_type' => BookingRateType::from($type['rate_type'])->value,
                'price' => (float) $type['price'],
                'account_id' => $nullableInt($type['account_id'] ?? null),
                'cost_center_id' => $nullableInt($type['cost_center_id'] ?? null),
            ], $data['booking_types'] ?? []),
            isActive: (bool) ($data['is_active'] ?? true),
            includesAccounting: $includesAccounting,
        );
    }
}
