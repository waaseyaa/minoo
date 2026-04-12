<?php

declare(strict_types=1);

namespace App\Ingestion\EntityMapper;

use Waaseyaa\Foundation\SlugGenerator;
use App\Ingestion\ValueObject\SpeakerFields;

final class SpeakerMapper
{
    /** @param array<string, mixed> $data */
    public function map(array $data): SpeakerFields
    {
        $name = (string) ($data['name'] ?? '');

        return new SpeakerFields(
            name: $name,
            code: (string) ($data['code'] ?? ''),
            bio: isset($data['bio']) ? (string) $data['bio'] : null,
            slug: SlugGenerator::generate($name),
            status: 1,
            createdAt: time(),
            updatedAt: time(),
        );
    }

    public static function fromCode(string $code): SpeakerFields
    {
        return new SpeakerFields(
            name: $code,
            code: $code,
            bio: null,
            slug: $code,
            status: 1,
            createdAt: time(),
            updatedAt: time(),
        );
    }
}
