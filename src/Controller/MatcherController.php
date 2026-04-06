<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\GameStatsCalculator;
use Minoo\Support\LayoutTwigContext;
use Minoo\Support\MatcherEngine;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\Response;

final class MatcherController
{
    use GameControllerTrait;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly GateInterface $gate,
    ) {}

    private function getEntityTypeManager(): EntityTypeManager
    {
        return $this->entityTypeManager;
    }

    /** Render the game page. */
    public function page(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('matcher.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/games/matcher',
        ]));

        return new Response($html);
    }

    /** GET /api/games/matcher/daily — today's matching pairs. */
    public function daily(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $today = date('Y-m-d');
        $difficulty = 'easy';
        $direction = 'ojibwe_to_english';
        $count = MatcherEngine::pairCount($difficulty);

        $entries = $this->loadDictionaryEntries(500);
        if ($entries === []) {
            return $this->json(['error' => 'No words available'], 503);
        }

        $seed = MatcherEngine::dailySeed($today);
        $pairs = MatcherEngine::selectPairs($entries, $count, $seed);

        if (count($pairs) < $count) {
            return $this->json(['error' => 'Not enough words available'], 503);
        }

        $session = $this->createMatcherSession($account, 'daily', $direction, $difficulty, $today, $pairs);

        return $this->json([
            'session_token' => $session->get('uuid'),
            'pairs' => $this->shuffledPairSides($pairs, $direction),
            'difficulty' => $difficulty,
            'direction' => $direction,
            'date' => $today,
        ]);
    }

    /** GET /api/games/matcher/practice — random pairs by difficulty. */
    public function practice(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $difficulty = $query['difficulty'] ?? 'easy';
        if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $difficulty = 'easy';
        }

        $direction = $query['direction'] ?? 'ojibwe_to_english';
        if (!in_array($direction, ['ojibwe_to_english', 'english_to_ojibwe'], true)) {
            $direction = 'ojibwe_to_english';
        }

        $count = MatcherEngine::pairCount($difficulty);

        $entries = $this->loadDictionaryEntries(500);
        if ($entries === []) {
            return $this->json(['error' => 'No words available'], 503);
        }

        $pairs = MatcherEngine::selectPairs($entries, $count);

        if (count($pairs) < $count) {
            return $this->json(['error' => 'Not enough words available'], 503);
        }

        $session = $this->createMatcherSession($account, 'practice', $direction, $difficulty, null, $pairs);

        return $this->json([
            'session_token' => $session->get('uuid'),
            'pairs' => $this->shuffledPairSides($pairs, $direction),
            'difficulty' => $difficulty,
            'direction' => $direction,
        ]);
    }

    /** POST /api/games/matcher/match — validate a single match attempt. */
    public function match(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';
        $leftId = $data['left_id'] ?? '';
        $rightId = $data['right_id'] ?? '';

        if ($token === '' || $leftId === '' || $rightId === '') {
            return $this->json(['error' => 'Missing required fields'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        if ($this->gate->denies('update', $session, $account)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        if ($session->get('status') !== 'in_progress') {
            return $this->json(['error' => 'Game already finished'], 400);
        }

        $pairs = json_decode((string) $session->get('guesses'), true) ?: [];
        $result = MatcherEngine::validateMatch($leftId, $rightId, $pairs);

        // Track attempt in session
        $matches = json_decode((string) ($session->get('grid_state') ?? '[]'), true) ?: [];
        $matches[] = ['left_id' => $leftId, 'right_id' => $rightId, 'correct' => $result['correct']];

        $wrongCount = (int) $session->get('wrong_count');
        if (!$result['correct']) {
            $wrongCount++;
        }

        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session->set('grid_state', json_encode($matches));
        $session->set('wrong_count', $wrongCount);
        $sessionStorage->save($session);

        return $this->json($result);
    }

    /** POST /api/games/matcher/complete — finish game, record stats. */
    public function complete(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';

        if ($token === '') {
            return $this->json(['error' => 'Missing session_token'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        if ($this->gate->denies('update', $session, $account)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session->set('status', 'completed');
        $sessionStorage->save($session);

        $matches = json_decode((string) ($session->get('grid_state') ?? '[]'), true) ?: [];
        $totalAttempts = count($matches);
        $wrongCount = (int) $session->get('wrong_count');
        $correctCount = $totalAttempts - $wrongCount;
        $accuracy = $totalAttempts > 0 ? round(($correctCount / $totalAttempts) * 100, 1) : 100.0;

        $timeSeconds = (int) $session->get('updated_at') - (int) $session->get('created_at');

        $stats = GameStatsCalculator::build(
            $this->entityTypeManager,
            $account,
            'matcher',
            streakBreakers: [],
            winStatuses: ['completed'],
        );

        return $this->json([
            'time_seconds' => $timeSeconds,
            'attempts' => $totalAttempts,
            'wrong_count' => $wrongCount,
            'accuracy' => $accuracy,
            'pairs_count' => $correctCount,
            'stats' => $stats,
        ]);
    }

    /** GET /api/games/matcher/stats — player stats. */
    public function stats(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->json(GameStatsCalculator::build(
            $this->entityTypeManager,
            $account,
            'matcher',
            streakBreakers: [],
            winStatuses: ['completed'],
        ));
    }

    // --- Private helpers ---

    /**
     * Load raw dictionary entry data for pair selection.
     *
     * @return list<array{id: int, word: string, definition: string}>
     */
    private function loadDictionaryEntries(int $limit): array
    {
        $storage = $this->entityTypeManager->getStorage('dictionary_entry');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->range(0, $limit)
            ->execute();

        if ($ids === []) {
            return [];
        }

        $entries = [];
        foreach ($storage->loadMultiple($ids) as $entity) {
            $entries[] = [
                'id' => $entity->id(),
                'word' => (string) $entity->get('word'),
                'definition' => (string) $entity->get('definition'),
            ];
        }

        return $entries;
    }

    /**
     * Create a matcher game session.
     *
     * @param list<array{id: int, ojibwe: string, english: string}> $pairs
     */
    private function createMatcherSession(
        AccountInterface $account,
        string $mode,
        string $direction,
        string $difficulty,
        ?string $dailyDate,
        array $pairs,
    ): \Minoo\Entity\GameSession {
        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session = $sessionStorage->create([
            'game_type' => 'matcher',
            'mode' => $mode,
            'direction' => $direction,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'daily_date' => $dailyDate,
            'difficulty_tier' => $difficulty,
            'guesses' => json_encode($pairs),
            'grid_state' => '[]',
        ]);
        $sessionStorage->save($session);

        assert($session instanceof \Minoo\Entity\GameSession);

        return $session;
    }

    /**
     * Return pairs with shuffled left/right columns for the frontend.
     *
     * Each side is independently shuffled so positions don't hint at matches.
     *
     * @param list<array{id: int, ojibwe: string, english: string}> $pairs
     * @return array{left: list<array{id: int, text: string}>, right: list<array{id: int, text: string}>}
     */
    private function shuffledPairSides(array $pairs, string $direction): array
    {
        $left = [];
        $right = [];

        foreach ($pairs as $pair) {
            if ($direction === 'ojibwe_to_english') {
                $left[] = ['id' => $pair['id'], 'text' => $pair['ojibwe']];
                $right[] = ['id' => $pair['id'], 'text' => $pair['english']];
            } else {
                $left[] = ['id' => $pair['id'], 'text' => $pair['english']];
                $right[] = ['id' => $pair['id'], 'text' => $pair['ojibwe']];
            }
        }

        shuffle($left);
        shuffle($right);

        return ['left' => $left, 'right' => $right];
    }
}
