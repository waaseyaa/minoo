<?php

declare(strict_types=1);

namespace App\Language;

use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * The own-corpus lexicon lookup behind GET /api/lang/lookup (issue #916).
 *
 * This is the LEXICON endpoint: it reads `dictionary_entry` but ONLY Minoo's own
 * community corpus (`attribution_source = 'corpus'`, the Sagamok Nishnaabemwin
 * lexicon). That equality filter is also the OPD exclusion: every Ojibwe People's
 * Dictionary row carries a different `attribution_source`, so no OPD content can
 * ever surface here. OPD is reference-only and is never served through /api/lang.
 *
 * Distinct from {@see TranslationMemoryService}, which backs /api/lang/translate:
 * that is the translation MEMORY (English -> community translation, exact then
 * fuzzy then gap-log). This service is the dictionary lexicon (a term in either
 * direction -> community entries), returns ALL matches rather than one best
 * translation, and never writes a gap-log row.
 *
 * Reads are consent-gated through the access-checked query (the request account
 * is bound, so LanguageAccessPolicy filters to published, public-consent rows).
 */
final class CorpusLexiconService
{
    /** Minimum similar_text percent for a fuzzy hit (mirrors the TM threshold). */
    public const int FUZZY_THRESHOLD = 70;

    /**
     * The corpus is the Sagamok community lexicon, so every match carries the
     * Sagamok community tag regardless of the row's stored `language_code` (the
     * corpus rows predate per-row community tagging and store plain `oj`).
     */
    public const string CORPUS_TAG = 'oj-x-sagamok';

    /** The own-corpus marker. Equality on this is also the OPD exclusion. */
    private const string CORPUS_SOURCE = 'corpus';

    /**
     * Cap on corpus rows scanned. A perf guard, not a silent truncation of a real
     * result set: the community lexicon is small (tens of rows). A large corpus
     * would want a trigram index instead of an in-PHP scan.
     */
    private const int SCAN_LIMIT = 1000;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly DialectCodeProvider $dialects,
    ) {
    }

    /**
     * Look up a term in the community corpus. `$dir` is 'en' (the term is English,
     * match the definitions), 'oj' (the term is Anishinaabemowin, match the word),
     * or null (match both sides). Exact matches first; if none, fuzzy matches.
     *
     * @return array<string, mixed>
     */
    public function lookup(string $term, ?string $tag, ?string $dir, AccountInterface $account): array
    {
        $normalized = self::normalize($term);
        if ($normalized === '') {
            return $this->envelope('invalid', $term, $tag, $dir, []);
        }

        $tag = $tag !== null && $tag !== '' ? $tag : null;

        // The corpus is oj-x-sagamok. A requested tag that is neither tag-agnostic,
        // the corpus tag, nor in the same derived dialect grouping has no corpus
        // content, so there is nothing to return (a 200 miss, not a 422 — the tag
        // is well-formed, it just selects no community we hold).
        if ($tag !== null && !$this->tagSelectsCorpus($tag)) {
            return $this->envelope('miss', $term, $tag, $dir, []);
        }

        $exact = [];
        $fuzzy = [];
        foreach ($this->loadCorpusRows($account) as $row) {
            $match = $this->matchRow($row, $normalized, $dir);
            if ($match === null) {
                continue;
            }
            if ($match['match_type'] === 'exact') {
                $exact[] = $match;
            } else {
                $fuzzy[] = $match;
            }
        }

        if ($exact !== []) {
            return $this->envelope('exact', $term, $tag, $dir, $this->sortMatches($exact));
        }
        if ($fuzzy !== []) {
            return $this->envelope('fuzzy', $term, $tag, $dir, $this->sortMatches($fuzzy));
        }

        return $this->envelope('miss', $term, $tag, $dir, []);
    }

    /**
     * Normalize a term for matching: trim, collapse internal whitespace, lowercase.
     */
    public static function normalize(string $term): string
    {
        $collapsed = (string) preg_replace('/\s+/', ' ', trim($term));

        return mb_strtolower($collapsed);
    }

    /**
     * The corpus rows (own corpus only; OPD excluded by the equality filter),
     * consent-gated via the access-checked query.
     *
     * @return list<EntityInterface>
     */
    private function loadCorpusRows(AccountInterface $account): array
    {
        $repository = $this->entityTypeManager->getRepository('dictionary_entry');
        $ids = $repository->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->condition('attribution_source', self::CORPUS_SOURCE)
            ->execute();
        if ($ids === []) {
            // Consent-lock intent (#788): an empty id set must never widen to the
            // whole table (which would leak OPD rows into the corpus-only
            // endpoint). findMany([]) is fail-closed upstream now, so this guard
            // is redundant — kept as defense in depth documenting the invariant.
            return [];
        }

        $ids = array_slice($ids, 0, self::SCAN_LIMIT);

        return $repository->findMany($ids);
    }

    /**
     * Match one corpus row against the normalized term in the requested direction.
     *
     * @return array<string, mixed>|null
     */
    private function matchRow(EntityInterface $row, string $normalizedTerm, ?string $dir): ?array
    {
        $word = (string) $row->get('word');
        $wordNorm = self::normalize($word);
        $senses = $this->definitionSenses($row);
        $matchSenses = $this->splitSenses($senses);

        $checkOj = $dir === null || $dir === 'oj';
        $checkEn = $dir === null || $dir === 'en';

        // Exact: the term equals the word (oj side) or any english (sub)sense. The
        // term is always non-empty (the caller guards), so an empty word never
        // produces a spurious exact hit.
        if ($checkOj && $wordNorm === $normalizedTerm) {
            return $this->buildMatch($row, $word, $senses, 'exact', 100, 'oj');
        }
        if ($checkEn) {
            foreach ($matchSenses as $sense) {
                if (self::normalize($sense) === $normalizedTerm) {
                    return $this->buildMatch($row, $word, $senses, 'exact', 100, 'en');
                }
            }
        }

        // Fuzzy: the best similar_text percent across the considered sides
        // (similarity() returns 0 for an empty side, so no guard is needed).
        $bestScore = 0;
        $bestSide = null;
        if ($checkOj) {
            $score = $this->similarity($normalizedTerm, $wordNorm);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSide = 'oj';
            }
        }
        if ($checkEn) {
            foreach ($matchSenses as $sense) {
                $score = $this->similarity($normalizedTerm, self::normalize($sense));
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestSide = 'en';
                }
            }
        }

        if ($bestSide !== null && $bestScore >= self::FUZZY_THRESHOLD) {
            return $this->buildMatch($row, $word, $senses, 'fuzzy', $bestScore, $bestSide);
        }

        return null;
    }

    /**
     * @param list<string> $senses
     *
     * @return array<string, mixed>
     */
    private function buildMatch(EntityInterface $row, string $word, array $senses, string $type, int $score, string $side): array
    {
        $grouping = $this->dialects->dialectFor(self::CORPUS_TAG);

        return [
            'word' => $word,
            'definition' => $senses,
            'tag' => self::CORPUS_TAG,
            'dialect' => $grouping !== null ? $grouping['name'] : 'Nishnaabemwin',
            'label' => $this->dialects->label(self::CORPUS_TAG),
            'slug' => (string) $row->get('slug'),
            'match_type' => $type,
            'match_score' => $score,
            'matched_on' => $side,
            'provenance' => [
                'attribution_source' => self::CORPUS_SOURCE,
                'attribution' => 'Sagamok community Knowledge Keepers',
                'source_url' => (string) $row->get('attribution_url'),
            ],
        ];
    }

    /**
     * The decoded english senses for display (the `definition` field is a
     * JSON-encoded array such as `["cup, glass"]`).
     *
     * @return list<string>
     */
    private function definitionSenses(EntityInterface $row): array
    {
        $raw = (string) $row->get('definition');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        $list = is_array($decoded) ? $decoded : [$raw];

        $out = [];
        foreach ($list as $item) {
            $sense = trim((string) $item);
            if ($sense !== '') {
                $out[] = $sense;
            }
        }

        return $out;
    }

    /**
     * Display senses split on commas into atomic sub-senses for matching, so a
     * query for "cup" matches a `["cup, glass"]` entry.
     *
     * @param list<string> $senses
     *
     * @return list<string>
     */
    private function splitSenses(array $senses): array
    {
        $out = [];
        foreach ($senses as $sense) {
            foreach (explode(',', $sense) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $out[] = $part;
                }
            }
        }

        return array_values(array_unique($out));
    }

    private function similarity(string $a, string $b): int
    {
        if ($a === '' || $b === '') {
            return 0;
        }
        $percent = 0.0;
        similar_text($a, $b, $percent);

        return (int) round($percent);
    }

    /**
     * Whether a well-formed tag selects the community corpus: tag-agnostic (`oj`),
     * the corpus tag itself, or any tag in the same derived dialect grouping.
     */
    private function tagSelectsCorpus(string $tag): bool
    {
        if ($tag === 'oj' || $tag === self::CORPUS_TAG) {
            return true;
        }
        $corpusGrouping = $this->dialects->dialectCodeForTag(self::CORPUS_TAG);
        $requested = $this->dialects->dialectCodeForTag($tag);

        return $requested !== null && $requested === $corpusGrouping;
    }

    /**
     * Order matches deterministically: higher score first, then by word.
     *
     * @param list<array<string, mixed>> $matches
     *
     * @return list<array<string, mixed>>
     */
    private function sortMatches(array $matches): array
    {
        usort($matches, static function (array $a, array $b): int {
            return ((int) $b['match_score'] <=> (int) $a['match_score'])
                ?: strcmp((string) $a['word'], (string) $b['word']);
        });

        return $matches;
    }

    /**
     * @param list<array<string, mixed>> $matches
     *
     * @return array<string, mixed>
     */
    private function envelope(string $matchType, string $term, ?string $tag, ?string $dir, array $matches): array
    {
        return [
            'match_type' => $matchType,
            'query' => $term,
            'tag' => $tag,
            'dir' => $dir,
            'count' => count($matches),
            'matches' => $matches,
        ];
    }
}
