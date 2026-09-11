<?php

namespace App\DTOs;

final readonly class CreateEmployeeData
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $jobTitle,
        public float $salary,
        public string $hireDate,
        public bool $isActive = true,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'] ?? null,
            jobTitle: $data['job_title'] ?? null,
            salary: (float) $data['salary'],
            hireDate: $data['hire_date'],
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
