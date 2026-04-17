<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\GameStatsCalculator;
use App\Support\LayoutTwigContext;
use Waaseyaa\Entity\ContentEntityBase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\Response;

final class AgimController
{
    use GameControllerTrait;

    /** Maps level → difficulty_tier used in GameSession. */
    private const TIERS = [
        1 => 'easy',
        2 => 'medium',
        3 => 'hard',
        4 => 'streak',
    ];

    /** Maps level → [first_numeral, last_numeral]. */
    private const LEVEL_RANGES = [
        1 => [1, 5],
        2 => [1, 10],
        3 => [1, 15],
        4 => [1, 19],
    ];

    /** All 19 cardinal numbers: numeral → [word, deid]. */
    private const NUMBERS = [
        1  => ['word' => 'bezhig',            'deid' => 4578],
        2  => ['word' => 'niizh',             'deid' => 16239],
        3  => ['word' => 'niswi',             'deid' => 16928],
        4  => ['word' => 'niiwin',            'deid' => 16158],
        5  => ['word' => 'naanan',            'deid' => 15013],
        6  => ['word' => 'ningodwaaswi',      'deid' => 16582],
        7  => ['word' => 'niizhwaaswi',       'deid' => 16355],
        8  => ['word' => 'nishwaaswi',        'deid' => 16810],
        9  => ['word' => 'zhaangaswi',        'deid' => 20922],
        10 => ['word' => 'midaaswi',          'deid' => 13612],
        11 => ['word' => 'ashi-bezhig',       'deid' => 2355],
        12 => ['word' => 'ashi-niizh',        'deid' => 2378],
        13 => ['word' => 'ashi-niswi',        'deid' => 2393],
        14 => ['word' => 'ashi-niiwin',       'deid' => 2375],
        15 => ['word' => 'ashi-naanan',       'deid' => 2371],
        16 => ['word' => 'ashi-ningodwaaswi', 'deid' => 2387],
        17 => ['word' => 'ashi-niizhwaaswi',  'deid' => 2383],
        18 => ['word' => 'ashi-nishwaaswi',   'deid' => 2390],
        19 => ['word' => 'ashi-zhaangaswi',   'deid' => 2396],
    ];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly GateInterface $gate,
    ) {}

    private function getEntityTypeManager(): EntityTypeManager
    {
        return $this->entityTypeManager;
    }

    /** Override trait to accept ContentEntityBase (GameSession is final, unmockable in tests). */
    private function loadSessionByToken(string $uuid): ?ContentEntityBase
    {
        $entity = $this->entityTypeManager->getStorage('game_session')->loadByKey('uuid', $uuid);
        return $entity instanceof ContentEntityBase ? $entity : null;
    }

    /** Render the Agim game page. */
    public function page(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('pages/games/agim.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/games/agim',
        ]));
        return new Response($html);
    }

    /**
     * GET /api/games/agim/start?level={1-4}
     * Creates a new game session for the requested level.
     */
    public function start(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $raw = (int) ($query['level'] ?? 1);
        $level = isset(self::TIERS[$raw]) ? $raw : 1;
        $tier = self::TIERS[$level];
        [$first, $last] = self::LEVEL_RANGES[$level];
        $numerals = range($first, $last);
        shuffle($numerals);

        $storage = $this->entityTypeManager->getStorage('game_session');
        $session = $storage->create([
            'game_type' => 'agim',
            'mode' => 'practice',
            'difficulty_tier' => $tier,
            'status' => 'in_progress',
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'guesses' => json_encode(['queue' => $numerals, 'completed' => []]),
        ]);
        $storage->save($session);

        return $this->json([
            'session_token' => $session->get('uuid'),
            'level' => $level,
            'total' => count($numerals),
            'numeral' => $numerals[0],
        ]);
    }

    /**
     * GET /api/games/agim/prompt?session_token=X
     * Returns the next numeral to answer.
     */
    public function prompt(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $token = $query['session_token'] ?? '';
        if ($token === '') {
            return $this->json(['error' => 'Missing session_token'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        $guesses = json_decode((string) $session->get('guesses'), true);
        $queue = $guesses['queue'] ?? [];

        if ($queue === []) {
            return $this->json(['error' => 'Session already complete'], 400);
        }

        return $this->json([
            'numeral' => $queue[0],
            'remaining' => count($queue),
        ]);
    }

    /**
     * POST /api/games/agim/answer
     * Body: {session_token, numeral, answer}
     * Validates the answer, updates session state.
     */
    public function answer(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';
        $numeral = (int) ($data['numeral'] ?? 0);
        $answer = (string) ($data['answer'] ?? '');

        if ($token === '' || $numeral === 0 || $answer === '') {
            return $this->json(['error' => 'Missing session_token, numeral, or answer'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        if ($this->gate->denies('update', $session, $account)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        if (!isset(self::NUMBERS[$numeral])) {
            return $this->json(['error' => 'Unknown numeral'], 400);
        }

        $expected = self::NUMBERS[$numeral]['word'];
        $correct = $this->normalizeWord($answer) === $this->normalizeWord($expected);

        $guesses = json_decode((string) $session->get('guesses'), true);
        $queue = $guesses['queue'] ?? [];
        $completed = $guesses['completed'] ?? [];

        // Remove from queue regardless of correctness
        $queue = array_values(array_filter($queue, fn(int $n) => $n !== $numeral));

        if ($correct) {
            $completed[] = $numeral;
        } else {
            $queue[] = $numeral; // re-queue at end
        }

        $session->set('guesses', json_encode(['queue' => $queue, 'completed' => $completed]));

        if ($queue === []) {
            $session->set('status', 'completed');
        }

        $this->entityTypeManager->getStorage('game_session')->save($session);

        return $this->json([
            'correct' => $correct,
            'expected_word' => $expected,
            'deid' => self::NUMBERS[$numeral]['deid'],
            'remaining' => count($queue),
        ]);
    }

    /**
     * POST /api/games/agim/complete
     * Body: {session_token}
     * Returns teaching data for all numbers in the level.
     */
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

        if ((string) $session->get('status') !== 'completed') {
            return $this->json(['error' => 'Session not yet completed'], 400);
        }

        // Determine level from tier
        $tier = (string) $session->get('difficulty_tier');
        $level = (int) (array_search($tier, self::TIERS, true) ?: 1);
        [$first, $last] = self::LEVEL_RANGES[$level];
        $numerals = range($first, $last);

        // Batch-load dictionary entries
        $deids = array_map(fn(int $n) => self::NUMBERS[$n]['deid'], $numerals);
        $entries = $this->entityTypeManager->getStorage('dictionary_entry')->loadMultiple($deids);

        $teachings = [];
        foreach ($numerals as $n) {
            $entry = $entries[self::NUMBERS[$n]['deid']] ?? null;
            $teaching = [
                'numeral' => $n,
                'word' => self::NUMBERS[$n]['word'],
            ];
            if ($entry !== null) {
                $teaching['meaning'] = $this->cleanDefinition((string) $entry->get('definition'));
            }
            $teachings[] = $teaching;
        }

        $stats = GameStatsCalculator::build($this->entityTypeManager, $account, 'agim', ['abandoned'], ['completed']);
        $timeSeconds = time() - (int) $session->get('created_at');

        return $this->json([
            'completed' => true,
            'time_seconds' => $timeSeconds,
            'teachings' => $teachings,
            'stats' => $stats,
        ]);
    }

    /** GET /api/games/agim/stats — auth required (enforced at route level). */
    public function stats(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->json(
            GameStatsCalculator::build($this->entityTypeManager, $account, 'agim', ['abandoned'], ['completed']),
        );
    }

    /**
     * Lowercase + strip diacritics for comparison.
     * Handles both ASCII and Unicode long-vowel markers.
     */
    private function normalizeWord(string $input): string
    {
        $lower = mb_strtolower(trim($input));
        $decomposed = \Normalizer::normalize($lower, \Normalizer::FORM_D);
        return (string) preg_replace('/\p{Mn}/u', '', $decomposed);
    }
}
