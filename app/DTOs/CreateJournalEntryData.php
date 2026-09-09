<?php

namespace App\DTOs;

use App\Enums\JournalSourceType;

final readonly class CreateJournalEntryData
{
    /**
     * @param  JournalLineData[]  $lines
     */
    public function __construct(
        public string $date,
        public string $description,
        public ?string $reference,
        public JournalSourceType $sourceType,
        public ?int $sourceId,
        public int $createdBy,
        public array $lines,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            description: $data['description'],
            reference: $data['reference'] ?? null,
            sourceType: JournalSourceType::from($data['source_type'] ?? 'manual'),
            sourceId: isset($data['source_id']) ? (int) $data['source_id'] : null,
            createdBy: (int) $data['created_by'],
            lines: array_map(
                fn (array $line) => JournalLineData::fromArray($line),
                $data['lines'],
            ),
        );
    }
}
