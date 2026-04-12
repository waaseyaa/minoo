<?php

declare(strict_types=1);

namespace App\Ingestion\ValueObject;

final readonly class CulturalCollectionFields
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $sourceAttribution,
        public string $sourceUrl,
        public string $slug,
        public int $status,
        public int $createdAt,
        public int $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'source_attribution' => $this->sourceAttribution,
            'source_url' => $this->sourceUrl,
            'slug' => $this->slug,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
