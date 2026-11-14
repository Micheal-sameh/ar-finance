<?php

namespace App\Modules\Donations\DTOs;

final readonly class SaveDonorData
{
    public function __construct(
        public string $name,
        public ?string $phone,
        public ?string $email,
        public ?string $notes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $optional = fn (string $key): ?string => isset($data[$key]) && trim((string) $data[$key]) !== '' ? trim((string) $data[$key]) : null;

        return new self(
            name: trim((string) $data['name']),
            phone: $optional('phone'),
            email: $optional('email'),
            notes: $optional('notes'),
        );
    }
}
