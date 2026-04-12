<?php

declare(strict_types=1);

namespace App\Ingestion\ValueObject;

final readonly class WordPartFields
{
    public function __construct(
        public string $form,
        public string $type,
        public string $definition,
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
            'form' => $this->form,
            'type' => $this->type,
            'definition' => $this->definition,
            'source_url' => $this->sourceUrl,
            'slug' => $this->slug,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
