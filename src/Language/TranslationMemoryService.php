<?php

declare(strict_types=1);

namespace App\Language;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * The translation-memory lookup contract (issue #894, tracker Section C):
 * exact match first, then fuzzy, then write or increment a gap-log row on a miss.
 *
 * Reads are consent-gated through the access-checked query (the request account
 * is bound, so LanguageAccessPolicy filters to published, public-consent rows).
 * The gap-log write is a system-context path (operational miss log, admin-only),
 * so it binds no account and opts out of per-row access checks; see
 * docs/security/sql-entity-query-access-check-bypass-audit.md.
 */
final class TranslationMemoryService
{
    /** Minimum similar_text percent for a fuzzy hit. */
    public const int FUZZY_THRESHOLD = 70;

    /**
     * Cap on candidate rows scanned for fuzzy matching. A perf guard, not a
     * silent truncation of correctness: exact lookup is hash-indexed and never
     * capped; only the fuzzy fallback bounds its in-PHP similarity scan. A real
     * deployment would back this with a trigram index.
     */
    private const int FUZZY_SCAN_LIMIT = 200;

    public function __construct(private readonly EntityTypeManager $entityTypeManager)
    {
    }

    /**
     * Look up an English string for the given dialect (or dialect-agnostic when
     * null): exact, then fuzzy, then log the gap.
     *
     * @return array<string, mixed>
     */
    public function lookup(string $english, ?string $dialect, AccountInterface $account): array
    {
        $normalized = self::normalize($english);
        if ($normalized === '') {
            return ['match_type' => 'invalid', 'query' => $english];
        }

        $exact = $this->findExact($normalized, $dialect, $account);
        if ($exact !== null) {
            return $this->hit('exact', $exact, null);
        }

        $fuzzy = $this->findFuzzy($normalized, $dialect, $account);
        if ($fuzzy !== null) {
            return $this->hit('fuzzy', $fuzzy['entity'], $fuzzy['score']);
        }

        $this->logGap($normalized, $english, $dialect);

        return [
            'match_type' => 'miss',
            'query' => $english,
            'dialect' => $dialect,
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

    private function findExact(string $normalized, ?string $dialect, AccountInterface $account): ?EntityInterface
    {
        $storage = $this->entityTypeManager->getStorage('translation_memory');
        $ids = $storage->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->condition('source_hash', self::hash($normalized))
            ->execute();
        if ($ids === []) {
            return null;
        }

        return $this->pickByDialect(array_values($storage->loadMultiple($ids)), $dialect);
    }

    /**
     * @return array{entity: EntityInterface, score: int}|null
     */
    private function findFuzzy(string $normalized, ?string $dialect, AccountInterface $account): ?array
    {
        $storage = $this->entityTypeManager->getStorage('translation_memory');
        $query = $storage->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->condition('consent_public', 1);
        if ($dialect !== null && $dialect !== '') {
            $query->condition('dialect_code', $dialect);
        }
        $ids = $query->execute();
        if ($ids === []) {
            return null;
        }
        $ids = array_slice($ids, 0, self::FUZZY_SCAN_LIMIT);

        $best = null;
        $bestScore = 0;
        foreach (array_values($storage->loadMultiple($ids)) as $row) {
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
     * @param list<EntityInterface> $rows
     */
    private function pickByDialect(array $rows, ?string $dialect): ?EntityInterface
    {
        if ($rows === []) {
            return null;
        }
        $dialect = $dialect !== null && $dialect !== '' ? $dialect : null;

        if ($dialect !== null) {
            foreach ($rows as $row) {
                if ((string) $row->get('dialect_code') === $dialect) {
                    return $row;
                }
            }
        }

        foreach ($rows as $row) {
            if ((string) $row->get('dialect_code') === '') {
                return $row;
            }
        }

        // A dialect was requested but only other-dialect rows exist: no match.
        return $dialect === null ? $rows[0] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function hit(string $type, EntityInterface $entity, ?int $matchScore): array
    {
        $dialect = (string) $entity->get('dialect_code');

        return array_filter([
            'match_type' => $type,
            'translation' => (string) $entity->get('translation'),
            'dialect' => $dialect !== '' ? $dialect : null,
            'confidence' => (int) $entity->get('confidence'),
            'needs_speaker_review' => (int) $entity->get('needs_speaker_review') === 1,
            'source' => (string) $entity->get('source_en'),
            'match_score' => $matchScore,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function logGap(string $normalized, string $original, ?string $dialect): void
    {
        $storage = $this->entityTypeManager->getStorage('tm_gap_log');
        $hash = self::hash($normalized);
        $dialectCode = $dialect !== null && $dialect !== '' ? $dialect : '';
        $now = time();

        // System-context write: the gap log is admin-only and dedup needs no
        // end-user account, so bind none and opt out of per-row access checks.
        // See docs/security/sql-entity-query-access-check-bypass-audit.md.
        $ids = $storage->getQuery()->accessCheck(false)
            ->condition('source_hash', $hash)
            ->condition('dialect_code', $dialectCode)
            ->execute();

        if ($ids !== []) {
            $gap = $storage->load(reset($ids));
            if ($gap !== null) {
                $gap->set('request_count', (int) $gap->get('request_count') + 1);
                $gap->set('last_requested_at', $now);
                $gap->set('updated_at', $now);
                $storage->save($gap);

                return;
            }
        }

        $gap = $storage->create([
            'source_en' => $original,
            'source_hash' => $hash,
            'dialect_code' => $dialectCode,
            'lookup_type' => 'exact_miss',
            'request_count' => 1,
            'last_requested_at' => $now,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $storage->save($gap);
    }
}
