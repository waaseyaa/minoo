<?php

declare(strict_types=1);

namespace App\Seed;

use Waaseyaa\Entity\EntityTypeManager;

/**
 * Idempotent upsert of the curated translation backlog into `tm_backlog`
 * (issue #906). Reads the committed seed dataset
 * (`config/translation_backlog_seed.json`, produced by the dev generator) and
 * writes one row per English surface form, keyed unique on
 * (english_text, target_tag).
 *
 * Re-runnable: an existing row is updated in place (demand counts, concept,
 * category refreshed) rather than duplicated, so seeding prod after a re-crawl
 * never doubles the backlog. Rows that staff have already graduated to
 * `translated` are left at their lifecycle status.
 *
 * Reads/writes use accessCheck(false): seeding is a trusted admin/CLI operation,
 * not a request-scoped read (see the bypass audit doc).
 */
final class TranslationBacklogSeeder
{
    public const string PROVENANCE = 'seed-crawl-2026-06-25';
    public const string DEFAULT_TARGET_TAG = 'oj-x-sagamok';

    public function __construct(private readonly EntityTypeManager $entityTypeManager)
    {
    }

    /**
     * @param list<array{english_text: string, concept_key: string, demand_sites: int, demand_total: int, category: string}> $rows
     *
     * @return array{created: int, updated: int, total: int, by_category: array<string, int>}
     */
    public function seed(array $rows, string $targetTag = self::DEFAULT_TARGET_TAG): array
    {
        $repository = $this->entityTypeManager->getRepository('tm_backlog');
        $now = time();
        $created = 0;
        $updated = 0;
        $byCategory = [];

        foreach ($rows as $row) {
            $text = trim((string) $row['english_text']);
            if ($text === '') {
                continue;
            }
            $category = (string) $row['category'];
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
            $dedupeKey = self::dedupeKey($text, $targetTag);

            $existingIds = $repository->getQuery()
                ->accessCheck(false)
                ->condition('dedupe_key', $dedupeKey)
                ->execute();

            $fields = [
                'english_text' => $text,
                'concept_key' => (string) $row['concept_key'],
                'demand_sites' => (int) $row['demand_sites'],
                'demand_total' => (int) $row['demand_total'],
                'category' => $category,
                'target_tag' => $targetTag,
                'dedupe_key' => $dedupeKey,
                'provenance' => self::PROVENANCE,
                'updated_at' => $now,
            ];

            if ($existingIds !== []) {
                $entity = $repository->find((string) reset($existingIds));
                if ($entity !== null) {
                    foreach ($fields as $key => $value) {
                        $entity->set($key, $value);
                    }
                    $repository->save($entity);
                    ++$updated;
                    continue;
                }
            }

            $fields['status'] = 'awaiting_translation';
            $fields['created_at'] = $now;
            $repository->save($repository->create($fields));
            ++$created;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => $created + $updated,
            'by_category' => $byCategory,
        ];
    }

    /** Stable unique key for (english_text, target_tag). */
    public static function dedupeKey(string $englishText, string $targetTag): string
    {
        return hash('sha256', mb_strtolower(trim($englishText)) . '|' . $targetTag);
    }
}
