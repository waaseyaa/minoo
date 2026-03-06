<?php

declare(strict_types=1);

namespace Minoo\Ingest\EntityMapper;

use Minoo\Ingest\SlugGenerator;
use Minoo\Ingest\ValueObject\WordPartFields;

final class WordPartMapper
{
    private const array VALID_ROLES = ['initial', 'medial', 'final'];

    /** @param array<string, mixed> $data */
    public function map(array $data, string $sourceUrl): ?WordPartFields
    {
        $role = (string) ($data['morphological_role'] ?? '');
        if (!in_array($role, self::VALID_ROLES, true)) {
            return null;
        }

        $form = (string) ($data['form'] ?? '');

        return new WordPartFields(
            form: $form,
            type: $role,
            definition: (string) ($data['definition'] ?? ''),
            sourceUrl: $sourceUrl,
            slug: SlugGenerator::generate($form),
            status: 0,
            createdAt: time(),
            updatedAt: time(),
        );
    }
}
