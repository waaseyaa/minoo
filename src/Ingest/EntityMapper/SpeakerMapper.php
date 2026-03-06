<?php

declare(strict_types=1);

namespace Minoo\Ingest\EntityMapper;

final class SpeakerMapper
{
    /** @return array<string, mixed> */
    public function map(array $data): array
    {
        $name = (string) ($data['name'] ?? '');

        return [
            'name' => $name,
            'code' => (string) ($data['code'] ?? ''),
            'bio' => isset($data['bio']) ? (string) $data['bio'] : null,
            'slug' => DictionaryEntryMapper::generateSlug($name),
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }

    /** @return array<string, mixed> */
    public static function fromCode(string $code): array
    {
        return [
            'name' => $code,
            'code' => $code,
            'bio' => null,
            'slug' => $code,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }
}
