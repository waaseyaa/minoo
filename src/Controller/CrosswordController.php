<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\CrosswordEngine;
use Minoo\Support\GameStatsCalculator;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class CrosswordController
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

    /** Render the crossword game page. */
    public function page(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('crossword.html.twig', [
            'path' => '/games/crossword',
        ]);
        return new SsrResponse(content: $html);
    }

    /** GET /api/games/crossword/daily — today's puzzle. */
    public function daily(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $today = date('Y-m-d');
        $puzzleId = "daily-{$today}";

        $puzzleStorage = $this->entityTypeManager->getStorage('crossword_puzzle');
        $puzzle = $puzzleStorage->load($puzzleId);

        if ($puzzle === null) {
            // Fallback: generate on-the-fly when cron missed a run
            $puzzle = $this->generateFallbackDaily($puzzleId, $today);
            if ($puzzle === null) {
                return $this->json(['error' => 'No words available to generate puzzle'], 503);
            }
        }

        $payload = $this->loadPuzzlePayload($puzzle);
        $session = $this->createGameSession('daily', $puzzleId, $payload['tier'], $account, $today);

        return $this->json([
            'session_token' => $session->get('uuid'),
            'puzzle_id' => $puzzleId,
            'grid_size' => (int) $puzzle->get('grid_size'),
            'placements' => $this->buildClientPlacements($payload['words']),
            'clues' => $payload['clues'],
            'word_bank' => $payload['word_bank'],
            'difficulty' => $payload['tier'],
            'max_hints' => CrosswordEngine::maxHints($payload['tier']),
            'date' => $today,
        ]);
    }

    /** GET /api/games/crossword/random — random practice puzzle. */
    public function random(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $tier = $query['tier'] ?? 'easy';
        if (!in_array($tier, ['easy', 'medium', 'hard'], true)) {
            $tier = 'easy';
        }

        $puzzleStorage = $this->entityTypeManager->getStorage('crossword_puzzle');
        $ids = $puzzleStorage->getQuery()
            ->condition('difficulty_tier', $tier)
            ->execute();

        // Exclude daily puzzles and filter for non-themed practice grids
        $practiceIds = array_filter($ids, fn($id) => !str_starts_with((string) $id, 'daily-'));

        if ($practiceIds === []) {
            // Fallback: use any puzzle at this tier
            $practiceIds = $ids;
        }

        if ($practiceIds === []) {
            return $this->json(['error' => 'No puzzles available'], 503);
        }

        $puzzleId = $practiceIds[array_rand($practiceIds)];
        $puzzle = $puzzleStorage->load($puzzleId);

        if ($puzzle === null) {
            return $this->json(['error' => 'Puzzle not found'], 503);
        }

        $payload = $this->loadPuzzlePayload($puzzle);
        $session = $this->createGameSession('practice', (string) $puzzleId, $tier, $account);

        return $this->json([
            'session_token' => $session->get('uuid'),
            'puzzle_id' => (string) $puzzleId,
            'grid_size' => (int) $puzzle->get('grid_size'),
            'placements' => $this->buildClientPlacements($payload['words']),
            'clues' => $payload['clues'],
            'word_bank' => $payload['word_bank'],
            'difficulty' => $tier,
            'max_hints' => CrosswordEngine::maxHints($tier),
        ]);
    }

    /** GET /api/games/crossword/themes — list theme packs with progress. */
    public function themes(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $puzzleStorage = $this->entityTypeManager->getStorage('crossword_puzzle');
        $allIds = $puzzleStorage->getQuery()->execute();

        // Group by theme field (not by parsing IDs)
        $themeCounts = [];
        $puzzles = $puzzleStorage->loadMultiple($allIds);
        foreach ($puzzles as $puzzle) {
            $theme = (string) $puzzle->get('theme');
            if ($theme === '') {
                continue;
            }
            $themeCounts[$theme] = ($themeCounts[$theme] ?? 0) + 1;
        }

        // Load user's completed crossword sessions once (not per-theme)
        $completedByTheme = [];
        if ($account->isAuthenticated()) {
            $sessionIds = $this->entityTypeManager->getStorage('game_session')->getQuery()
                ->condition('game_type', 'crossword')
                ->condition('user_id', $account->id())
                ->condition('status', 'completed')
                ->execute();
            $sessions = $this->entityTypeManager->getStorage('game_session')->loadMultiple($sessionIds);
            foreach ($sessions as $s) {
                $pid = (string) $s->get('puzzle_id');
                // Match puzzle_id to theme by loading the puzzle's theme
                foreach ($puzzles as $p) {
                    if ((string) $p->id() === $pid) {
                        $t = (string) $p->get('theme');
                        if ($t !== '') {
                            $completedByTheme[$t] = ($completedByTheme[$t] ?? 0) + 1;
                        }
                        break;
                    }
                }
            }
        }

        $themes = [];
        foreach ($themeCounts as $slug => $total) {
            $entry = ['slug' => $slug, 'name' => ucfirst($slug), 'total' => $total];
            if ($account->isAuthenticated()) {
                $entry['completed'] = $completedByTheme[$slug] ?? 0;
            }
            $themes[] = $entry;
        }

        return $this->json(['themes' => $themes]);
    }

    /** GET /api/games/crossword/theme/{slug} — next unsolved puzzle in theme. */
    public function theme(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $slug = $params['slug'] ?? '';
        if ($slug === '') {
            return $this->json(['error' => 'Missing theme slug'], 400);
        }

        $puzzleStorage = $this->entityTypeManager->getStorage('crossword_puzzle');
        $allIds = $puzzleStorage->getQuery()
            ->condition('theme', $slug)
            ->execute();

        if ($allIds === []) {
            return $this->json(['error' => 'Theme not found'], 404);
        }

        // Determine completed puzzles
        $completedPuzzleIds = [];
        if ($account->isAuthenticated()) {
            $sessionIds = $this->entityTypeManager->getStorage('game_session')->getQuery()
                ->condition('game_type', 'crossword')
                ->condition('user_id', $account->id())
                ->condition('status', 'completed')
                ->execute();
            $sessions = $this->entityTypeManager->getStorage('game_session')->loadMultiple($sessionIds);
            foreach ($sessions as $s) {
                $completedPuzzleIds[] = (string) $s->get('puzzle_id');
            }
        } else {
            // Anonymous: client sends completed IDs as query param
            $completed = $query['completed'] ?? '';
            if ($completed !== '') {
                $completedPuzzleIds = explode(',', $completed);
            }
        }

        // Find first unsolved
        sort($allIds);
        $nextId = null;
        foreach ($allIds as $id) {
            if (!in_array((string) $id, $completedPuzzleIds, true)) {
                $nextId = $id;
                break;
            }
        }

        if ($nextId === null) {
            return $this->json(['error' => 'All puzzles in this theme completed', 'theme_complete' => true], 200);
        }

        $puzzle = $puzzleStorage->load($nextId);
        if ($puzzle === null) {
            return $this->json(['error' => 'Puzzle not found'], 503);
        }

        $payload = $this->loadPuzzlePayload($puzzle);
        $session = $this->createGameSession('themed', (string) $nextId, $payload['tier'], $account);

        return $this->json([
            'session_token' => $session->get('uuid'),
            'puzzle_id' => (string) $nextId,
            'grid_size' => (int) $puzzle->get('grid_size'),
            'placements' => $this->buildClientPlacements($payload['words']),
            'clues' => $payload['clues'],
            'word_bank' => $payload['word_bank'],
            'difficulty' => $payload['tier'],
            'max_hints' => CrosswordEngine::maxHints($payload['tier']),
            'theme' => $slug,
        ]);
    }

    /** POST /api/games/crossword/check — validate a word. */
    public function check(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';
        $wordIndex = $data['word_index'] ?? null;
        $letters = $data['letters'] ?? [];

        if ($token === '' || $wordIndex === null || !is_array($letters)) {
            return $this->json(['error' => 'Missing session_token, word_index, or letters'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        if ($this->gate->denies('update', $session, $account)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $puzzleId = (string) $session->get('puzzle_id');
        $puzzle = $this->entityTypeManager->getStorage('crossword_puzzle')->load($puzzleId);
        if ($puzzle === null) {
            return $this->json(['error' => 'Puzzle not found'], 500);
        }

        $words = json_decode((string) $puzzle->get('words'), true) ?: [];
        $wordIndex = (int) $wordIndex;
        if (!isset($words[$wordIndex])) {
            return $this->json(['error' => 'Invalid word index'], 400);
        }

        $correctWord = $words[$wordIndex]['word'];
        $result = CrosswordEngine::validateWord($letters, $correctWord);

        // Update grid state
        $gridState = json_decode((string) ($session->get('grid_state') ?? '{}'), true) ?: [];
        if ($result['correct']) {
            $gridState["word_{$wordIndex}"] = 'completed';
        }
        $session->set('grid_state', json_encode($gridState));

        // Check if all words completed
        $allComplete = true;
        foreach (array_keys($words) as $idx) {
            if (($gridState["word_{$idx}"] ?? '') !== 'completed') {
                $allComplete = false;
                break;
            }
        }

        if ($allComplete) {
            $session->set('status', 'completed');
        }

        $this->entityTypeManager->getStorage('game_session')->save($session);

        $response = [
            'correct' => $result['correct'],
            'word_index' => $wordIndex,
            'correct_positions' => $result['correct_positions'],
            'wrong_positions' => $result['wrong_positions'],
            'puzzle_complete' => $allComplete,
        ];

        // Include teaching data for correct words
        if ($result['correct'] && isset($words[$wordIndex]['dictionary_entry_id'])) {
            $entry = $this->entityTypeManager->getStorage('dictionary_entry')
                ->load((int) $words[$wordIndex]['dictionary_entry_id']);
            if ($entry !== null) {
                $response['teaching'] = [
                    'word' => (string) $entry->get('word'),
                    'meaning' => $this->cleanDefinition((string) $entry->get('definition')),
                    'pos' => (string) $entry->get('part_of_speech'),
                ];
            }
        }

        return $this->json($response);
    }

    /** POST /api/games/crossword/complete — submit finished puzzle. */
    public function complete(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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

        if ($session->get('status') !== 'completed') {
            return $this->json(['error' => 'Puzzle not yet completed'], 400);
        }

        $puzzleId = (string) $session->get('puzzle_id');
        $puzzle = $this->entityTypeManager->getStorage('crossword_puzzle')->load($puzzleId);
        if ($puzzle === null) {
            return $this->json(['error' => 'Puzzle not found'], 500);
        }

        $words = json_decode((string) $puzzle->get('words'), true) ?: [];
        $cluesData = json_decode((string) $puzzle->get('clues'), true) ?: [];

        // Batch-load dictionary entries to avoid N+1 queries
        $entryIds = array_filter(array_column($words, 'dictionary_entry_id'));
        $dictEntries = $entryIds !== []
            ? $this->entityTypeManager->getStorage('dictionary_entry')->loadMultiple($entryIds)
            : [];

        // Build teaching data for each word
        $wordTeachings = [];
        foreach ($words as $idx => $w) {
            $teaching = ['word' => $w['word']];

            if (isset($w['dictionary_entry_id'])) {
                $entry = $dictEntries[(int) $w['dictionary_entry_id']] ?? null;
                if ($entry !== null) {
                    $teaching['meaning'] = $this->cleanDefinition((string) $entry->get('definition'));
                    $teaching['pos'] = (string) $entry->get('part_of_speech');

                    // Load example sentence
                    $exampleIds = $this->entityTypeManager->getStorage('example_sentence')->getQuery()
                        ->condition('dictionary_entry_id', $entry->id())
                        ->condition('status', 1)
                        ->range(0, 1)
                        ->execute();
                    $example = $exampleIds !== []
                        ? $this->entityTypeManager->getStorage('example_sentence')->load(reset($exampleIds))
                        : null;
                    if ($example !== null) {
                        $teaching['example_ojibwe'] = (string) $example->get('ojibwe_text');
                        $teaching['example_english'] = (string) $example->get('english_text');
                    }
                }
            }

            // Include elder clue attribution
            $clueData = $cluesData[(string) $idx] ?? null;
            if ($clueData !== null && ($clueData['elder'] ?? null) !== null) {
                $teaching['elder_clue'] = $clueData['elder'];
                $teaching['elder_author'] = $clueData['elder_author'] ?? null;
            }

            $wordTeachings[] = $teaching;
        }

        $stats = GameStatsCalculator::build($this->entityTypeManager, $account, 'crossword', ['abandoned'], ['completed']);

        // Compute time from session creation to now
        $timeSeconds = time() - (int) $session->get('created_at');

        return $this->json([
            'completed' => true,
            'time_seconds' => $timeSeconds,
            'hints_used' => (int) $session->get('hints_used'),
            'words' => $wordTeachings,
            'stats' => $stats,
        ]);
    }

    /** GET /api/games/crossword/stats — player stats (auth required). */
    public function stats(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        return $this->json(GameStatsCalculator::build($this->entityTypeManager, $account, 'crossword', ['abandoned'], ['completed']));
    }

    /** POST /api/games/crossword/hint — reveal a letter (tracked server-side). */
    public function hint(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $data = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';
        $wordIndex = $data['word_index'] ?? null;
        $position = $data['position'] ?? null;

        if ($token === '' || $wordIndex === null || $position === null) {
            return $this->json(['error' => 'Missing required fields'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        if ($this->gate->denies('update', $session, $account)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $tier = (string) $session->get('difficulty_tier');
        $maxHints = CrosswordEngine::maxHints($tier);
        $hintsUsed = (int) $session->get('hints_used');

        if ($maxHints !== -1 && $hintsUsed >= $maxHints) {
            return $this->json(['error' => 'No hints remaining'], 400);
        }

        $puzzle = $this->entityTypeManager->getStorage('crossword_puzzle')
            ->load((string) $session->get('puzzle_id'));
        if ($puzzle === null) {
            return $this->json(['error' => 'Puzzle not found'], 500);
        }

        $words = json_decode((string) $puzzle->get('words'), true) ?: [];
        $wordIndex = (int) $wordIndex;
        if (!isset($words[$wordIndex])) {
            return $this->json(['error' => 'Invalid word index'], 400);
        }

        $word = $words[$wordIndex]['word'];
        $position = (int) $position;
        $letter = mb_substr(mb_strtolower($word), $position, 1);

        $session->set('hints_used', $hintsUsed + 1);
        $this->entityTypeManager->getStorage('game_session')->save($session);

        return $this->json([
            'letter' => $letter,
            'position' => $position,
            'word_index' => $wordIndex,
            'hints_remaining' => $maxHints === -1 ? -1 : $maxHints - $hintsUsed - 1,
        ]);
    }

    /** POST /api/games/crossword/abandon — give up on current puzzle. */
    public function abandon(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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

        if ($session->get('status') !== 'in_progress') {
            return $this->json(['error' => 'Game already finished'], 400);
        }

        $session->set('status', 'abandoned');
        $this->entityTypeManager->getStorage('game_session')->save($session);

        return $this->json(['abandoned' => true]);
    }

    // --- Private helpers ---

    /**
     * Decode puzzle words/clues, resolve clues, and build word bank.
     * @return array{words: list<array>, clues: array<string, mixed>, word_bank: list<array>|null, tier: string}
     */
    private function loadPuzzlePayload(object $puzzle): array
    {
        $tier = (string) $puzzle->get('difficulty_tier');
        $words = json_decode((string) $puzzle->get('words'), true) ?: [];
        $cluesData = json_decode((string) $puzzle->get('clues'), true) ?: [];

        $clues = [];
        foreach ($cluesData as $idx => $clueData) {
            $clues[$idx] = CrosswordEngine::resolveClue($clueData);
        }

        return [
            'words' => $words,
            'clues' => $clues,
            'word_bank' => $this->buildWordBank($words, $tier),
            'tier' => $tier,
        ];
    }

    /**
     * Create and persist a crossword game session.
     */
    private function createGameSession(
        string $mode,
        string $puzzleId,
        string $tier,
        AccountInterface $account,
        ?string $dailyDate = null,
    ): object {
        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $values = [
            'game_type' => 'crossword',
            'mode' => $mode,
            'puzzle_id' => $puzzleId,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'difficulty_tier' => $tier,
        ];
        if ($dailyDate !== null) {
            $values['daily_date'] = $dailyDate;
        }
        $session = $sessionStorage->create($values);
        $sessionStorage->save($session);
        return $session;
    }

    /**
     * Strip answer data from word placements for client consumption.
     * @param list<array{row: int, col: int, direction: string, word: string}> $words
     * @return list<array{row: int, col: int, direction: string, length: int}>
     */
    private function buildClientPlacements(array $words): array
    {
        return array_map(fn($w) => [
            'row' => $w['row'],
            'col' => $w['col'],
            'direction' => $w['direction'],
            'length' => mb_strlen($w['word']),
        ], $words);
    }

    /**
     * Generate a fallback daily puzzle when cron missed a run.
     * Uses dictionary entries to build a quick grid on the fly.
     */
    private function generateFallbackDaily(string $puzzleId, string $today): ?object
    {
        $dayOfWeek = (int) date('w', strtotime($today));
        $tier = \Minoo\Support\GameDifficulty::dailyTier($dayOfWeek);

        // Load dictionary words with definitions
        $dictStorage = $this->entityTypeManager->getStorage('dictionary_entry');
        $ids = $dictStorage->getQuery()
            ->condition('status', 1)
            ->range(0, 200)
            ->execute();

        $words = [];
        $wordMeta = [];
        $entries = $dictStorage->loadMultiple($ids);
        foreach ($entries as $entry) {
            $word = mb_strtolower((string) $entry->get('word'));
            $def = (string) $entry->get('definition');
            $len = mb_strlen($word);
            if ($def === '' || $len < 3 || $len > 7 || str_contains($word, '-')) {
                continue;
            }
            $words[] = $word;
            $wordMeta[$word] = [
                'dictionary_entry_id' => (int) $entry->id(),
                'definition' => $def,
            ];
        }

        $result = CrosswordEngine::generateGrid($words, 7, 4);
        if ($result === null) {
            return null;
        }

        // Build puzzle data
        $puzzleWords = [];
        $clues = [];
        foreach ($result['placements'] as $idx => $p) {
            $meta = $wordMeta[$p['word']] ?? null;
            $puzzleWords[] = [
                'dictionary_entry_id' => $meta['dictionary_entry_id'] ?? null,
                'row' => $p['row'],
                'col' => $p['col'],
                'direction' => $p['direction'],
                'word' => $p['word'],
            ];
            $clues[(string) $idx] = [
                'auto' => $meta !== null ? $this->cleanDefinition($meta['definition']) : $p['word'],
                'elder' => null,
                'elder_author' => null,
            ];
        }

        $puzzleStorage = $this->entityTypeManager->getStorage('crossword_puzzle');
        $puzzle = $puzzleStorage->create([
            'id' => $puzzleId,
            'grid_size' => 7,
            'words' => json_encode($puzzleWords),
            'clues' => json_encode($clues),
            'difficulty_tier' => $tier,
        ]);
        $puzzleStorage->save($puzzle);

        return $puzzle;
    }

    /**
     * Build word bank based on difficulty tier.
     *
     * @param list<array{dictionary_entry_id?: int, word: string}> $words
     * @return list<array{word: string, meaning?: string}>|null
     */
    private function buildWordBank(array $words, string $tier): ?array
    {
        if ($tier === 'hard') {
            return null; // No word bank on hard
        }

        // Batch-load dictionary entries to avoid N+1 queries
        $dictEntries = [];
        if ($tier === 'easy') {
            $entryIds = array_filter(array_column($words, 'dictionary_entry_id'));
            if ($entryIds !== []) {
                $dictEntries = $this->entityTypeManager->getStorage('dictionary_entry')
                    ->loadMultiple($entryIds);
            }
        }

        $bank = [];
        foreach ($words as $w) {
            $entry = ['word' => $w['word']];
            if ($tier === 'easy' && isset($w['dictionary_entry_id'])) {
                $dictEntry = $dictEntries[(int) $w['dictionary_entry_id']] ?? null;
                if ($dictEntry !== null) {
                    $entry['meaning'] = $this->cleanDefinition((string) $dictEntry->get('definition'));
                }
            }
            $bank[] = $entry;
        }

        // Shuffle so word bank order doesn't match clue order
        shuffle($bank);
        return $bank;
    }

}
