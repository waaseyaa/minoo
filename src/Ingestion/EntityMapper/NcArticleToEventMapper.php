<?php

declare(strict_types=1);

namespace Minoo\Ingestion\EntityMapper;

use Waaseyaa\Foundation\SlugGenerator;

final class NcArticleToEventMapper
{
    /**
     * Map a NorthCloud search hit to event entity fields.
     *
     * @param array<string, mixed> $hit NC search result item
     * @return array<string, mixed> Fields ready for EntityStorage::create()
     */
    public function map(array $hit): array
    {
        $title = (string) ($hit['title'] ?? '');
        $description = (string) ($hit['snippet'] ?? $hit['body'] ?? '');
        $sourceUrl = (string) ($hit['url'] ?? '');

        return [
            'title' => $title,
            'slug' => SlugGenerator::generate($title),
            'type' => 'gathering',
            'description' => $description,
            'source_url' => $sourceUrl,
            'copyright_status' => 'external_link',
            'consent_public' => 1,
            'consent_ai_training' => 0,
            'status' => 1,
            'created_at' => $this->parseTimestamp($hit['published_date'] ?? null),
            'updated_at' => time(),
        ];
    }

    private function parseTimestamp(mixed $date): int
    {
        if (is_string($date) && $date !== '') {
            $ts = strtotime($date);
            if ($ts !== false) {
                return $ts;
            }
        }

        return time();
    }
}
