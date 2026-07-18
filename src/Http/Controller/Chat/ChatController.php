<?php

declare(strict_types=1);

namespace App\Http\Controller\Chat;

use App\Http\View\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * Cite-only language assistant (#822).
 *
 * Re-grounded from the old free-form Anthropic chat into a retrieval surface:
 * it answers ONLY by citing matched dictionary entries and (consent-public)
 * example sentences. It never generates Anishinaabemowin and needs no AI
 * provider. Retrieval is the same consent-gated LIKE search the dictionary
 * uses (no FTS5, Pi-friendly). Example sentences stay gated until the corpus
 * is published and consented (Phase 4), so today only the dictionary answers.
 */
final class ChatController
{
    /** Cap on candidates loaded for relevance ranking. */
    private const int SEARCH_CANDIDATES = 400;

    private const int MAX_ENTRIES = 8;

    private const int MAX_EXAMPLES = 6;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function index(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $q = trim((string) ($query['q'] ?? ''));

        $entries = $q !== '' ? $this->retrieveEntries($q, $account) : [];
        $examples = $q !== '' ? $this->retrieveExamples($q, $account) : [];

        $html = $this->twig->render('pages/chat/index.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/chat',
            'query' => $q,
            'entries' => $entries,
            'examples' => $examples,
            'has_answer' => $q !== '' && ($entries !== [] || $examples !== []),
        ]));

        return new Response($html);
    }

    /**
     * Dictionary entries matching the question, consent-public only, ranked
     * exact > prefix > substring(word) > definition-only.
     *
     * @return list<array<string, string>>
     */
    private function retrieveEntries(string $q, AccountInterface $account): array
    {
        $repository = $this->entityTypeManager->getRepository('dictionary_entry');
        $qLower = mb_strtolower($q);
        $like = '%' . addcslashes($q, '%_\\') . '%';

        $base = static fn () => $repository->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->condition('consent_public', 1);

        $wordIds = $base()->condition('word', $like, 'LIKE')->execute();
        $defIds = $base()->condition('definition', $like, 'LIKE')->execute();
        $ids = array_values(array_unique(array_merge($wordIds, $defIds)));

        // findMany([]) is fail-closed upstream now; keep the early return as
        // defense in depth so a no-match query never ranks the whole dictionary.
        $candidateIds = array_slice($ids, 0, self::SEARCH_CANDIDATES);
        if ($candidateIds === []) {
            return [];
        }

        $ranked = [];
        foreach ($repository->findMany($candidateIds) as $entry) {
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

        $results = [];
        foreach (array_slice($ranked, 0, self::MAX_ENTRIES) as $row) {
            $entry = $row['entry'];
            $results[] = [
                'word' => (string) $entry->get('word'),
                'slug' => (string) $entry->get('slug'),
                'part_of_speech' => (string) $entry->get('part_of_speech'),
                'definition' => (string) $entry->get('definition'),
                'language_code' => (string) ($entry->get('language_code') ?: 'oj'),
                'attribution_source' => (string) $entry->get('attribution_source'),
            ];
        }

        return $results;
    }

    /**
     * Example sentences matching the question. Consent-public only, so this is
     * empty until the corpus is published and consented (Phase 4).
     *
     * @return list<array<string, string>>
     */
    private function retrieveExamples(string $q, AccountInterface $account): array
    {
        if (!$this->entityTypeManager->hasDefinition('example_sentence')) {
            return [];
        }

        $repository = $this->entityTypeManager->getRepository('example_sentence');
        $like = '%' . addcslashes($q, '%_\\') . '%';

        $base = static fn () => $repository->getQuery()->setAccount($account)
            ->condition('status', 1)
            ->condition('consent_public', 1);

        $ojIds = $base()->condition('ojibwe_text', $like, 'LIKE')->execute();
        $enIds = $base()->condition('english_text', $like, 'LIKE')->execute();
        $ids = array_slice(array_values(array_unique(array_merge($ojIds, $enIds))), 0, self::MAX_EXAMPLES);

        if ($ids === []) {
            return [];
        }

        $results = [];
        foreach ($repository->findMany($ids) as $sentence) {
            $results[] = [
                'ojibwe_text' => (string) $sentence->get('ojibwe_text'),
                'english_text' => (string) $sentence->get('english_text'),
                'source_url' => (string) ($sentence->get('source_url') ?? ''),
                'audio_url' => (string) ($sentence->get('audio_url') ?? ''),
            ];
        }

        return $results;
    }
}
