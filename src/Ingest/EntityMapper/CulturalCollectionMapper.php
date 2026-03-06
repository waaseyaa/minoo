<?php

declare(strict_types=1);

namespace Minoo\Ingest\EntityMapper;

final class CulturalCollectionMapper
{
    /** @return array<string, mixed> */
    public function map(array $data, string $sourceUrl): array
    {
        $title = (string) ($data['title'] ?? '');
        $description = (string) ($data['description'] ?? '');
        $description = (string) preg_replace('/<\/(h[1-6]|p|div|li|br)>/i', ' </$1>', $description);
        $description = trim((string) preg_replace('/\s+/', ' ', strip_tags($description)));

        return [
            'title' => $title,
            'description' => $description,
            'source_attribution' => isset($data['source_attribution']) ? (string) $data['source_attribution'] : null,
            'source_url' => $sourceUrl,
            'slug' => DictionaryEntryMapper::generateSlug($title),
            'status' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }
}
