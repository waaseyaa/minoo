<?php

declare(strict_types=1);

namespace Minoo\Ingest\EntityMapper;

final class WordPartMapper
{
    private const array VALID_ROLES = ['initial', 'medial', 'final'];

    /** @return array<string, mixed>|null Null if morphological_role is invalid */
    public function map(array $data, string $sourceUrl): ?array
    {
        $role = (string) ($data['morphological_role'] ?? '');
        if (!in_array($role, self::VALID_ROLES, true)) {
            return null;
        }

        $form = (string) ($data['form'] ?? '');

        return [
            'form' => $form,
            'type' => $role,
            'definition' => (string) ($data['definition'] ?? ''),
            'source_url' => $sourceUrl,
            'slug' => DictionaryEntryMapper::generateSlug($form),
            'status' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }
}
