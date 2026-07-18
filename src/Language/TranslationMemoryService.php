<?php

declare(strict_types=1);

namespace App\Language;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * The translation-memory lookup contract (issues #894, #898): exact match first,
 * then fuzzy, then write or increment a gap-log row on a miss.
 *
 * Keyed on the full BCP 47 language tag (`language_tag`, e.g. `oj-x-sagamok`), so
 * community granularity is never lost. The exact fallback order is: the requested
 * tag, then any row in the same DERIVED dialect grouping, then the tag-agnostic
 * language row. A dialect grouping is never stored or keyed on; it is derived from
 * the tag by {@see DialectCodeProvider}.
 *
 * Reads are consent-gated through the access-checked query (the request account is
 * bound, so LanguageAccessPolicy filters to published, public-consent rows). The
 * gap-log write is a system-context path (operational miss log, admin-only), so it
 * binds no account and opts out of per-row access checks; see
 * docs/security/sql-entity-query-access-check-bypass-audit.md.
 */
final class TranslationMemoryService
{
    /** Minimum similar_text percent for a fuzzy hit. */
    public const int FUZZY_THRESHOLD = 70;

    /**
     * Cap on candidate rows scanned for fuzzy matching. A perf guard, not a silent
     * truncation of correctness: exact lookup is hash-indexed and never capped;
     * only the fuzzy fallback bounds its in-PHP similarity scan. A real deployment
     * would back this with a trigram index.
     */
    private const int FUZZY_SCAN_LIMIT = 200;

    /** Language tags treated as tag-agnostic (no community or dialect). */
    private const array AGNOSTIC_TAGS = ['', 'oj'];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly DialectCodeProvider $dialects,
    ) {
    }

    /**
     * Look up an English string for the given BCP 47 tag (or tag-agnostic when
     * null): exact, then fuzzy, then log the gap.
     *
     * @return array<string, mixed>
     */
    public function lookup(string $english, ?string $tag, AccountInterface $account): array
    {
        $normalized = self::normalize($english);
        if ($normalized === '') {
            return ['match_type' => 'invalid', 'query' => $english];
        }

        $tag = $tag !== null && $tag !== '' ? $tag : null;

        $exact = $this->findExact($normalized, $tag, $account);
        if ($exact !== null) {
            return $this->hit('exact', $exact, null);
        }

        $fuzzy = $this->findFuzzy($normalized, $tag, $account);
        if ($fuzzy !== null) {
            return $this->hit('fuzzy', $fuzzy['entity'], $fuzzy['score']);
        }

        $this->logGap($normalized, $english, $tag);

        return [
            'match_type' => 'miss',
            'query' => $english,
            'tag' => $tag,
            'logged' => true,
        ];
    }

    /**
     * Normalize an English string for matching and hashing: trim, collapse
     * internal whitespace, lowercase.
     */
    public static function normalize(string $english): string
    {
        $collapsed = (string) preg_replace('/\s+/', ' ', trim($english));

        return mb_strtolower($collapsed);
    }

    /**
     * The exact-lookup hash of an already-normalized string.
     */
    public static function hash(string $normalized): string
    {
        return hash('sha256', $normalized);
    }

    private function findExact(string $normalized, ?string $tag, AccountInterface $account): ?EntityInterface
    {
        $repository = $this->entityTypeManager->getRepository('translation_memory');
        $ids = $repository->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->condition('source_hash', self::hash($normalized))
            ->execute();
        if ($ids === []) {
            return null;
        }

        return $this->pickByTag($repository->findMany($ids), $tag);
    }

    /**
     * @return array{entity: EntityInterface, score: int}|null
     */
    private function findFuzzy(string $normalized, ?string $tag, AccountInterface $account): ?array
    {
        $repository = $this->entityTypeManager->getRepository('translation_memory');
        $query = $repository->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->condition('consent_public', 1);
        if ($tag !== null) {
            // Community-scoped fuzzy: a near-spelling within the same tag.
            $query->condition('language_tag', $tag);
        }
        $ids = $query->execute();
        if ($ids === []) {
            return null;
        }
        $ids = array_slice($ids, 0, self::FUZZY_SCAN_LIMIT);

        $best = null;
        $bestScore = 0;
        foreach ($repository->findMany($ids) as $row) {
            $source = self::normalize((string) $row->get('source_en'));
            $percent = 0.0;
            similar_text($normalized, $source, $percent);
            $score = (int) round($percent);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        return $best !== null && $bestScore >= self::FUZZY_THRESHOLD
            ? ['entity' => $best, 'score' => $bestScore]
            : null;
    }

    /**
     * Exact tag, then same derived dialect grouping, then the tag-agnostic row.
     *
     * @param list<EntityInterface> $rows
     */
    private function pickByTag(array $rows, ?string $tag): ?EntityInterface
    {
        if ($rows === []) {
            return null;
        }

        if ($tag !== null) {
            // 1. Exact tag.
            foreach ($rows as $row) {
                if ((string) $row->get('language_tag') === $tag) {
                    return $row;
                }
            }

            // 2. Same derived dialect grouping (another community, never a
            // dialect-only stored code).
            $grouping = $this->dialects->dialectCodeForTag($tag);
            if ($grouping !== null) {
                foreach ($rows as $row) {
                    $rowTag = (string) $row->get('language_tag');
                    if (in_array($rowTag, self::AGNOSTIC_TAGS, true)) {
                        continue;
                    }
                    if ($this->dialects->dialectCodeForTag($rowTag) === $grouping) {
                        return $row;
                    }
                }
            }
        }

        // 3. Tag-agnostic.
        foreach ($rows as $row) {
            if (in_array((string) $row->get('language_tag'), self::AGNOSTIC_TAGS, true)) {
                return $row;
            }
        }

        // A tag was requested but only other-grouping rows exist: no match.
        return $tag === null ? $rows[0] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function hit(string $type, EntityInterface $entity, ?int $matchScore): array
    {
        $rowTag = (string) $entity->get('language_tag');
        $grouping = $rowTag !== '' ? $this->dialects->dialectFor($rowTag) : null;

        return array_filter([
            'match_type' => $type,
            'translation' => (string) $entity->get('translation'),
            'tag' => $rowTag !== '' ? $rowTag : null,
            'dialect' => $grouping !== null ? $grouping['display_name'] : null,
            'label' => $rowTag !== '' ? $this->dialects->label($rowTag) : null,
            'confidence' => (int) $entity->get('confidence'),
            'needs_speaker_review' => (int) $entity->get('needs_speaker_review') === 1,
            'source' => (string) $entity->get('source_en'),
            'match_score' => $matchScore,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function logGap(string $normalized, string $original, ?string $tag): void
    {
        $repository = $this->entityTypeManager->getRepository('tm_gap_log');
        $hash = self::hash($normalized);
        $tagValue = $tag ?? '';
        $now = time();

        // System-context write: the gap log is admin-only and dedup needs no
        // end-user account, so bind none and opt out of per-row access checks.
        // See docs/security/sql-entity-query-access-check-bypass-audit.md.
        $ids = $repository->getQuery()->accessCheck(false)
            ->condition('source_hash', $hash)
            ->condition('language_tag', $tagValue)
            ->execute();

        if ($ids !== []) {
            $gap = $repository->find((string) reset($ids));
            if ($gap !== null) {
                $gap->set('request_count', (int) $gap->get('request_count') + 1);
                $gap->set('last_requested_at', $now);
                $gap->set('updated_at', $now);
                $repository->save($gap);

                return;
            }
        }

        $gap = $repository->create([
            'source_en' => $original,
            'source_hash' => $hash,
            'language_tag' => $tagValue,
            'lookup_type' => 'exact_miss',
            'request_count' => 1,
            'last_requested_at' => $now,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $repository->save($gap);
    }
}
