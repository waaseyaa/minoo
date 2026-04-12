<?php

declare(strict_types=1);

namespace App\Ingestion\EntityMapper;

use App\Ingestion\ValueObject\LeaderFields;

final class LeaderMapper
{
    /**
     * Map a NorthCloud leadership payload to Minoo entity fields.
     *
     * @param array<string, mixed> $data Payload data block
     * @param string $communityId Community nc_id reference
     */
    public function map(array $data, string $communityId): LeaderFields
    {
        return new LeaderFields(
            name: (string) ($data['name'] ?? ''),
            role: (string) ($data['role'] ?? $data['role_title'] ?? ''),
            email: isset($data['email']) ? (string) $data['email'] : null,
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            communityId: $communityId,
            status: 1,
            createdAt: time(),
            updatedAt: time(),
        );
    }
}
