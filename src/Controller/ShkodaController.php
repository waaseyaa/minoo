<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\GameStatsCalculator;
use App\Support\LayoutTwigContext;
use App\Support\ShkodaEngine;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class ShkodaController
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

    /** Redirect legacy /games/ishkode URL to /games/shkoda. */
    public function redirectLegacy(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return new RedirectResponse('/games/shkoda', 301);
    }

    /** Render the game page. */
    public function page(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('shkoda.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/games/shkoda',
        ]));

        return new Response($html);
    }

    /** GET /api/games/shkoda/daily — today's challenge metadata. */
    public function daily(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $today = date('Y-m-d');
        $dayOfWeek = (int) date('w');

        // Try pre-generated challenge
        $challengeStorage = $this->entityTypeManager->getStorage('daily_challenge');
        $challenge = $challengeStorage->load($today);

        if ($challenge !== null) {
            $entryId = (int) $challenge->get('dictionary_entry_id');
            $direction = (string) $challenge->get('direction');
            $tier = (string) $challenge->get('difficulty_tier');
        } else {
            // Fallback: deterministic random selection seeded by date
            $tier = \App\Support\GameDifficulty::dailyTier($dayOfWeek);
            $direction = $dayOfWeek % 2 === 0 ? 'english_to_ojibwe' : 'ojibwe_to_english';
            $entryId = $this->selectRandomWord($tier, $today);
            if ($entryId === null) {
                return $this->json(['error' => 'No words available for today'], 503);
            }
        }

        $entry = $this->entityTypeManager->getStorage('dictionary_entry')->load($entryId);
        if ($entry === null) {
            return $this->json(['error' => 'Word not found'], 503);
        }

        $word = (string) $entry->get('word');

        // Create server-side session for validation
        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session = $sessionStorage->create([
            'game_type' => 'shkoda',
            'mode' => 'daily',
            'direction' => $direction,
            'dictionary_entry_id' => $entryId,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'daily_date' => $today,
            'difficulty_tier' => $tier,
        ]);
        $sessionStorage->save($session);

        // English→Ojibwe: clue = definition (active recall)
        // Ojibwe→English: clue = POS hint only (spelling practice, meaning revealed at end)
        $pos = (string) $entry->get('part_of_speech');
        if ($direction === 'english_to_ojibwe') {
            $clue = $this->cleanDefinition((string) $entry->get('definition'));
            $clueDetail = $pos;
        } else {
            $clue = $pos !== '' ? $pos : 'Ojibwe word';
            $clueDetail = mb_strlen($word) . ' letters';
        }

        return $this->json([
            'session_token' => $session->get('uuid'),
            'word_length' => mb_strlen($word),
            'clue' => $clue,
            'clue_detail' => $clueDetail,
            'direction' => $direction,
            'difficulty' => $tier,
            'max_wrong' => ShkodaEngine::maxWrongGuesses($tier),
            'date' => $today,
            'free_positions' => $this->findFreePositions($word),
        ]);
    }

    /** GET /api/games/shkoda/word — random word for practice/streak. */
    public function word(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $mode = ($query['mode'] ?? 'practice') === 'streak' ? 'streak' : 'practice';
        $tier = $query['tier'] ?? 'easy';
        if (!in_array($tier, ['easy', 'medium', 'hard'], true)) {
            $tier = 'easy';
        }

        $entryId = $this->selectRandomWord($tier);
        if ($entryId === null) {
            return $this->json(['error' => 'No words available'], 503);
        }

        $entry = $this->entityTypeManager->getStorage('dictionary_entry')->load($entryId);
        if ($entry === null) {
            return $this->json(['error' => 'Word not found'], 503);
        }

        $direction = ($query['direction'] ?? 'english_to_ojibwe');
        if (!in_array($direction, ['english_to_ojibwe', 'ojibwe_to_english'], true)) {
            $direction = 'english_to_ojibwe';
        }

        $word = (string) $entry->get('word');

        // For practice/streak, include word (base64 obfuscated, client-side validation)
        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session = $sessionStorage->create([
            'game_type' => 'shkoda',
            'mode' => $mode,
            'direction' => $direction,
            'dictionary_entry_id' => $entryId,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'difficulty_tier' => $tier,
        ]);
        $sessionStorage->save($session);

        $pos = (string) $entry->get('part_of_speech');
        if ($direction === 'english_to_ojibwe') {
            $clue = $this->cleanDefinition((string) $entry->get('definition'));
            $clueDetail = $pos;
        } else {
            $clue = $pos !== '' ? $pos : 'Ojibwe word';
            $clueDetail = mb_strlen($word) . ' letters';
        }

        return $this->json([
            'session_token' => $session->get('uuid'),
            'word_length' => mb_strlen($word),
            'word_data' => base64_encode($word),
            'clue' => $clue,
            'clue_detail' => $clueDetail,
            'direction' => $direction,
            'difficulty' => $tier,
            'max_wrong' => ShkodaEngine::maxWrongGuesses($tier),
        ]);
    }

    /** POST /api/games/shkoda/guess — validate a letter (daily mode only). */
    public function guess(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data = $this->jsonBody($request);
        $token = $data['session_token'] ?? '';
        $letter = $data['letter'] ?? '';

        if ($token === '' || $letter === '') {
            return $this->json(['error' => 'Missing session_token or letter'], 422);
        }

        $session = $this->loadSessionByToken($token);
        if ($session === null) {
            return $this->json(['error' => 'Invalid session'], 404);
        }

        if ($session->get('mode') !== 'daily') {
            return $this->json(['error' => 'Guess endpoint is only for daily challenge mode'], 400);
        }

        if ($session->get('status') !== 'in_progress') {
            return $this->json(['error' => 'Game already finished'], 400);
        }

        // Load the word
        $entry = $this->entityTypeManager->getStorage('dictionary_entry')
            ->load((int) $session->get('dictionary_entry_id'));
        if ($entry === null) {
            return $this->json(['error' => 'Word not found'], 500);
        }

        $word = (string) $entry->get('word');
        $previousGuesses = json_decode((string) $session->get('guesses'), true) ?: [];

        $result = ShkodaEngine::processGuess($word, $letter, $previousGuesses);

        if (!empty($result['already_guessed'])) {
            return $this->json(['error' => 'Letter already guessed', 'already_guessed' => true], 400);
        }

        // Update session
        $previousGuesses[] = mb_strtolower($letter);
        $wrongCount = (int) $session->get('wrong_count');
        if (!$result['correct']) {
            $wrongCount++;
        }

        $maxWrong = ShkodaEngine::maxWrongGuesses((string) $session->get('difficulty_tier'));
        $allRevealed = $this->isWordFullyRevealed($word, $previousGuesses);
        $gameOver = $wrongCount >= $maxWrong || $allRevealed;

        $status = 'in_progress';
        if ($allRevealed) {
            $status = 'won';
        } elseif ($wrongCount >= $maxWrong) {
            $status = 'lost';
        }

        $sessionStorage = $this->entityTypeManager->getStorage('game_session');
        $session->set('guesses', json_encode($previousGuesses));
        $session->set('wrong_count', $wrongCount);
        $session->set('status', $status);
        $sessionStorage->save($session);

        $response = [
            'correct' => $result['correct'],
            'positions' => $result['positions'],
            'remaining_wrong' => $maxWrong - $wrongCount,
            'game_over' => $gameOver,
            'status' => $status,
        ];

        // Reveal word on game over
        if ($gameOver) {
            $response['word'] = $word;
        }

        return $this->json($response);
    }

    /** POST /api/games/shkoda/complete — submit completed game, get teaching data + stats. */
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

        // Verify session ownership via access policy
        if ($this->gate->denies('update', $session, $account)) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        // For practice/streak, accept client-reported result
        if ($session->get('mode') !== 'daily' && isset($data['result'])) {
            $result = $data['result'] === 'won' ? 'won' : 'lost';
            $guesses = $data['guesses'] ?? [];
            $wrongCount = (int) ($data['wrong_count'] ?? 0);

            $sessionStorage = $this->entityTypeManager->getStorage('game_session');
            $session->set('status', $result);
            $session->set('guesses', json_encode($guesses));
            $session->set('wrong_count', $wrongCount);
            $sessionStorage->save($session);
        }

        // Load word data for teaching moment
        $entry = $this->entityTypeManager->getStorage('dictionary_entry')
            ->load((int) $session->get('dictionary_entry_id'));

        if ($entry === null) {
            return $this->json(['error' => 'Word not found'], 500);
        }

        $word = (string) $entry->get('word');
        $slug = (string) $entry->get('slug');

        // Load example sentence if available
        $exampleStorage = $this->entityTypeManager->getStorage('example_sentence');
        $exampleIds = $exampleStorage->getQuery()
            ->condition('dictionary_entry_id', $entry->id())
            ->condition('status', 1)
            ->range(0, 1)
            ->execute();
        $example = $exampleIds !== [] ? $exampleStorage->load(reset($exampleIds)) : null;

        // Build stats for authenticated users
        $stats = GameStatsCalculator::build($this->entityTypeManager, $account, 'shkoda');

        return $this->json([
            'word' => $word,
            'definition' => $this->cleanDefinition((string) $entry->get('definition')),
            'part_of_speech' => (string) $entry->get('part_of_speech'),
            'stem' => (string) $entry->get('stem'),
            'slug' => $slug,
            'example_ojibwe' => $example !== null ? (string) $example->get('ojibwe_text') : null,
            'example_english' => $example !== null ? (string) $example->get('english_text') : null,
            'stats' => $stats,
        ]);
    }

    /** GET /api/games/shkoda/stats — player stats (auth required). */
    public function stats(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->json(GameStatsCalculator::build($this->entityTypeManager, $account, 'shkoda'));
    }

    // --- Private helpers ---

    private function selectRandomWord(string $tier, string $seed = ''): ?int
    {
        $storage = $this->entityTypeManager->getStorage('dictionary_entry');
        $query = $storage->getQuery()
            ->condition('status', 1)
            ->condition('consent_public', 1);

        $ids = $query->range(0, 500)->execute();

        if ($ids === []) {
            return null;
        }

        // Filter by word length for tier
        $filtered = [];
        $entries = $storage->loadMultiple($ids);
        foreach ($entries as $entry) {
            $word = (string) $entry->get('word');
            $pos = (string) $entry->get('part_of_speech');
            $def = (string) $entry->get('definition');

            if ($def === '') {
                continue;
            }

            $entryTier = ShkodaEngine::difficultyTier($word, $pos);
            if ($entryTier === $tier) {
                $filtered[] = $entry->id();
            }
        }

        if ($filtered === []) {
            // Fallback: any word with a definition
            $filtered = [];
            foreach ($entries as $entry) {
                if ((string) $entry->get('definition') !== '') {
                    $filtered[] = $entry->id();
                }
            }
        }

        if ($filtered === []) {
            return null;
        }

        // Shuffle to maintain randomness even with the 500 cap
        shuffle($filtered);

        // Deterministic selection for daily, random for practice
        if ($seed !== '') {
            $index = crc32($seed) % count($filtered);
            if ($index < 0) {
                $index += count($filtered);
            }
            return $filtered[$index];
        }

        return $filtered[array_rand($filtered)];
    }

    /** @param list<string> $guesses */
    private function isWordFullyRevealed(string $word, array $guesses): bool
    {
        $word = mb_strtolower($word);
        $len = mb_strlen($word);
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($word, $i, 1);
            // Skip non-guessable characters (punctuation, hyphens, spaces)
            if (!$this->isGuessableLetter($char)) {
                continue;
            }
            if (!in_array($char, $guesses, true)) {
                return false;
            }
        }
        return true;
    }

    /** Check if a character is a guessable letter (not punctuation/symbol). */
    private function isGuessableLetter(string $char): bool
    {
        return preg_match('/[\p{L}]/u', $char) === 1;
    }

    /**
     * Find positions of non-guessable characters to auto-reveal.
     * @return list<array{index: int, char: string}>
     */
    private function findFreePositions(string $word): array
    {
        $positions = [];
        $len = mb_strlen($word);
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($word, $i, 1);
            if (!$this->isGuessableLetter($char)) {
                $positions[] = ['index' => $i, 'char' => $char];
            }
        }
        return $positions;
    }

}
