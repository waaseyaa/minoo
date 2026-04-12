<?php

declare(strict_types=1);

namespace App\Ingestion\ValueObject;

final readonly class LeaderFields
{
    public function __construct(
        public string $name,
        public string $role,
        public ?string $email,
        public ?string $phone,
        public string $communityId,
        public int $status,
        public int $createdAt,
        public int $updatedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'role' => $this->role,
            'email' => $this->email,
            'phone' => $this->phone,
            'community_id' => $this->communityId,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
