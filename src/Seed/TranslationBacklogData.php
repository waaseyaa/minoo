<?php

declare(strict_types=1);

namespace App\Seed;

/**
 * Loads the committed translation-backlog seed dataset
 * (`config/translation_backlog_seed.json`, issue #906). The dataset is the
 * curated, demand-ranked output of the 2026-06-25 recon; it ships in the repo so
 * production seeds the same rows the dev run produced, without the raw recon
 * crawl ever being present on the server.
 */
final class TranslationBacklogData
{
    public const string SEED_FILE = 'config/translation_backlog_seed.json';

    /**
     * @return list<array{english_text: string, concept_key: string, demand_sites: int, demand_total: int, category: string}>
     */
    public static function load(string $projectRoot): array
    {
        $path = rtrim($projectRoot, '/\\') . '/' . self::SEED_FILE;
        if (!is_file($path)) {
            throw new \RuntimeException("Translation backlog seed file not found: {$path}");
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded) || !isset($decoded['rows']) || !is_array($decoded['rows'])) {
            throw new \RuntimeException("Malformed translation backlog seed file: {$path}");
        }

        /** @var list<array{english_text: string, concept_key: string, demand_sites: int, demand_total: int, category: string}> */
        return array_values($decoded['rows']);
    }
}
