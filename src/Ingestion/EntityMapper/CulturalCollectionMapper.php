<?php

declare(strict_types=1);

namespace App\Ingestion\EntityMapper;

use Waaseyaa\Foundation\SlugGenerator;
use App\Ingestion\ValueObject\CulturalCollectionFields;

final class CulturalCollectionMapper
{
    /** @param array<string, mixed> $data */
    public function map(array $data, string $sourceUrl): CulturalCollectionFields
    {
        $title = (string) ($data['title'] ?? '');
        $description = (string) ($data['description'] ?? '');
        $description = (string) preg_replace('/<\/(h[1-6]|p|div|li|br)>/i', ' </$1>', $description);
        $description = trim((string) preg_replace('/\s+/', ' ', strip_tags($description)));

        return new CulturalCollectionFields(
            title: $title,
            description: $description,
            sourceAttribution: isset($data['source_attribution']) ? (string) $data['source_attribution'] : null,
            sourceUrl: $sourceUrl,
            slug: SlugGenerator::generate($title),
            status: 0,
            createdAt: time(),
            updatedAt: time(),
        );
    }
}
