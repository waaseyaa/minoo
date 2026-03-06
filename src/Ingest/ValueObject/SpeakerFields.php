<?php

declare(strict_types=1);

namespace Minoo\Ingest\ValueObject;

final readonly class SpeakerFields
{
    public function __construct(
        public string $name,
        public string $code,
        public ?string $bio,
        public string $slug,
        public int $status,
        public int $createdAt,
        public int $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'bio' => $this->bio,
            'slug' => $this->slug,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
