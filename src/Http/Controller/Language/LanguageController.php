<?php

declare(strict_types=1);

namespace App\Http\Controller\Language;

use App\Domain\Account\SavedWords;
use App\Entity\Language\DictionaryEntry;
use App\Http\View\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

final class LanguageController
{
    private const int PAGE_SIZE = 50;

    /** Cap on candidates loaded for search relevance ranking. */
    private const int SEARCH_CANDIDATES = 400;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function list(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $offset = ($page - 1) * self::PAGE_SIZE;

        $storage = $this->entityTypeManager->getStorage('dictionary_entry');

        // #786 ordering: lead with real, defined words; de-rank entries whose
        // definition is empty ("[]") to the end so the browse opens on useful
        // content. A non-empty JSON definition always contains a quote char;
        // "[]" never does, which lets us split cheaply at the query layer.
        $base = static fn () => $storage->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->condition('consent_public', 1);

        $allSorted = $base()->sort('word', 'ASC')->execute();
        $withDef = $base()->condition('definition', '%"%', 'LIKE')->sort('word', 'ASC')->execute();
        $withDefSet = array_flip($withDef);
        $emptyDef = array_values(array_filter($allSorted, static fn ($id): bool => !isset($withDefSet[$id])));
        $allIds = array_merge($withDef, $emptyDef);

        $total = count($allIds);
        $pageIds = array_slice($allIds, $offset, self::PAGE_SIZE);
        $entries = $pageIds !== [] ? array_values($storage->loadMultiple($pageIds)) : [];
        $totalPages = (int) ceil($total / self::PAGE_SIZE);

        $html = $this->twig->render('pages/language/index.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/language',
            'entries' => $entries,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_entries' => $total,
        ]));

        return new Response($html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function show(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = $params['slug'] ?? '';
        $storage = $this->entityTypeManager->getStorage('dictionary_entry');
        $ids = $storage->getQuery()->setAccount($account)
            ->condition('slug', $slug)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->execute();
        $entry = $ids !== [] ? $storage->load(reset($ids)) : null;
        if (!$entry instanceof DictionaryEntry) {
            $entry = null;
        }

        $html = $this->twig->render('pages/language/show.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/language/' . $slug,
            'entry' => $entry,
            'inflected_forms' => $entry !== null ? $this->parseInflectedForms((string) $entry->get('inflected_forms')) : [],
            // #788 entry detail. Examples and related words are wired in a
            // consent-respecting, dialect-safe way; they render only when data
            // exists. The current Ojibwe People's Dictionary import carries no
            // linked examples, audio, or stems, so these are empty until the
            // Nishnaabemwin source work (#789) lands.
            'examples' => $entry !== null ? $this->examplesFor($account, (int) $entry->id()) : [],
            'related' => $entry !== null ? $this->relatedByStem($storage, $account, $entry) : [],
            // #806 personal word lists: show a save/unsave control to members.
            'entry_id' => $entry !== null ? (int) $entry->id() : 0,
            'is_saved' => $entry !== null && SavedWords::isSaved($this->entityTypeManager, $account, (int) $entry->id()),
        ]));

        return new Response($html, $entry !== null ? 200 : 404);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function search(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $q = trim((string) ($query['q'] ?? ''));

        $searchResults = [];
        $searchTotal = 0;
        $suggestion = '';

        if ($q !== '') {
            $storage = $this->entityTypeManager->getStorage('dictionary_entry');
            $qLower = mb_strtolower($q);
            $like = '%' . addcslashes($q, '%_\\') . '%';

            $base = static fn () => $storage->getQuery()->setAccount($account)
                ->condition('status', 1)
                ->condition('consent_public', 1);

            $wordIds = $base()->condition('word', $like, 'LIKE')->execute();
            $defIds = $base()->condition('definition', $like, 'LIKE')->execute();
            $ids = array_values(array_unique(array_merge($wordIds, $defIds)));
            $searchTotal = count($ids);

            // #787 relevance: exact word > prefix > substring(word) > definition-only.
            // Guard the empty set: loadMultiple([]) is the framework's "load all"
            // sentinel and would rank the entire dictionary for a no-match query.
            $ranked = [];
            $candidateIds = array_slice($ids, 0, self::SEARCH_CANDIDATES);
            foreach ($candidateIds === [] ? [] : $storage->loadMultiple($candidateIds) as $entry) {
                $word = (string) $entry->get('word');
                $wLower = mb_strtolower($word);
                if ($wLower === $qLower) {
                    $rank = 0;
                } elseif (str_starts_with($wLower, $qLower)) {
                    $rank = 1;
                } elseif (str_contains($wLower, $qLower)) {
                    $rank = 2;
                } else {
                    $rank = 3;
                }
                $ranked[] = ['rank' => $rank, 'sort' => $wLower, 'entry' => $entry];
            }
            usort($ranked, static fn (array $a, array $b): int => [$a['rank'], $a['sort']] <=> [$b['rank'], $b['sort']]);

            foreach (array_slice($ranked, 0, self::PAGE_SIZE) as $row) {
                $entry = $row['entry'];
                $searchResults[] = [
                    'word' => (string) $entry->get('word'),
                    'slug' => (string) $entry->get('slug'),
                    'part_of_speech' => (string) $entry->get('part_of_speech'),
                    'definition' => (string) $entry->get('definition'),
                    'language_code' => (string) ($entry->get('language_code') ?: 'oj'),
                    'attribution_source' => (string) $entry->get('attribution_source'),
                ];
            }

            // #787 did-you-mean: when nothing matched, suggest the closest
            // headword by edit distance over a cheap same-prefix candidate set.
            if ($searchTotal === 0) {
                $suggestion = $this->didYouMean($storage, $account, $q);
            }
        }

        $html = $this->twig->render('pages/language/search.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/language/search',
            'search_query' => $q,
            'search_results' => $searchResults,
            'search_total' => $searchTotal,
            'suggestion' => $suggestion,
        ]));

        return new Response($html);
    }

    /**
     * Closest headword to a misspelled query, by Levenshtein distance over a
     * bounded same-prefix candidate set (keeps it cheap on 21k entries).
     */
    private function didYouMean(object $storage, AccountInterface $account, string $q): string
    {
        if ($q === '') {
            return '';
        }

        $candidates = static fn (string $p, int $limit): array => $storage->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->condition('word', addcslashes($p, '%_\\') . '%', 'LIKE')
            ->range(0, $limit)
            ->execute();

        // Prefer the sharpest prefix that still yields candidates: a tighter
        // prefix keeps the true neighbour inside the bounded set (a 2-char "ma"
        // window can push "makwa" past the cap, suggesting a worse match).
        $best = '';
        $bestDistance = PHP_INT_MAX;
        $needle = mb_strtolower($q);
        foreach ([3, 2, 1] as $prefixLength) {
            if (mb_strlen($q) < $prefixLength) {
                continue;
            }
            $ids = $candidates(mb_substr($q, 0, $prefixLength), self::SEARCH_CANDIDATES);
            if ($ids === []) {
                continue;
            }
            foreach ($storage->loadMultiple($ids) as $entry) {
                $word = (string) $entry->get('word');
                $distance = levenshtein($needle, mb_strtolower($word));
                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $best = $word;
                }
            }
            break;
        }

        // Only suggest a genuinely close word.
        return $bestDistance > 0 && $bestDistance <= 3 ? $best : '';
    }

    /**
     * Consent-respecting example sentences linked to a dictionary entry.
     *
     * @return list<array{ojibwe: string, english: string}>
     */
    private function examplesFor(AccountInterface $account, int $entryId): array
    {
        if ($entryId <= 0) {
            return [];
        }

        $storage = $this->entityTypeManager->getStorage('example_sentence');
        $ids = $storage->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->condition('dictionary_entry_id', (string) $entryId)
            ->range(0, 5)
            ->execute();

        // Empty id set means no consent-public examples link to this entry.
        // Guard before loadMultiple(): an empty array is the framework's
        // "load all" sentinel and would surface the gated corpus (#788 leak).
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach ($storage->loadMultiple($ids) as $sentence) {
            $out[] = [
                'ojibwe' => (string) $sentence->get('ojibwe_text'),
                'english' => (string) $sentence->get('english_text'),
            ];
        }

        return $out;
    }

    /**
     * Related entries that share the same stem (morphological family). Empty
     * until entries carry stems; never mixes across dialects implicitly.
     *
     * @return list<array{word: string, slug: string}>
     */
    private function relatedByStem(object $storage, AccountInterface $account, EntityInterface $entry): array
    {
        $stem = trim((string) $entry->get('stem'));
        if ($stem === '') {
            return [];
        }

        $ids = $storage->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->condition('stem', $stem)
            ->range(0, 9)
            ->execute();

        // Guard the framework's "load all" sentinel (empty array) before
        // loadMultiple() so a stem with no matches never returns the whole table.
        if ($ids === []) {
            return [];
        }

        $self = (int) $entry->id();
        $out = [];
        foreach ($storage->loadMultiple($ids) as $other) {
            if ((int) $other->id() === $self) {
                continue;
            }
            $out[] = [
                'word' => (string) $other->get('word'),
                'slug' => (string) $other->get('slug'),
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function parseInflectedForms(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [$raw];
        }

        $items = [];
        foreach ($decoded as $item) {
            if (is_string($item) && trim($item) !== '') {
                $items[] = trim($item);
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $form = trim((string) ($item['form'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            if ($form === '') {
                continue;
            }

            $items[] = $label !== '' ? sprintf('%s: %s', $label, $form) : $form;
        }

        return $items;
    }
}
