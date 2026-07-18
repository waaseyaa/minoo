<?php

declare(strict_types=1);

namespace App\Http\Controller\Games;

use App\Domain\Games\GameStatsCalculator;
use App\Domain\Games\LearnableWord;
use App\Domain\Games\ShkodaEngine;
use App\Http\View\LayoutTwigContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

final class ShkodaController
{
    use GameControllerTrait;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly GateInterface $gate,
    ) {
    }

    private function getEntityTypeManager(): EntityTypeManager
    {
        return $this->entityTypeManager;
    }

    /** Redirect legacy /games/ishkode URL to /games/shkoda. */
    public function redirectLegacy(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return new RedirectResponse('/games/shkoda', 301);
    }

    /** Render the game page. */
    public function page(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('pages/games/shkoda.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/games/shkoda',
        ]));

        return new Response($html);
    }

    /** GET /api/games/shkoda/daily — today's challenge metadata. */
    public function daily(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $today = date('Y-m-d');
        $dayOfWeek = (int) date('w');

        // Try pre-generated challenge
        $challenge = $this->entityTypeManager->getRepository('daily_challenge')->find($today);

        if ($challenge !== null) {
            $entryId = (int) $challenge->get('dictionary_entry_id');
            $direction = (string) $challenge->get('direction');
            $tier = (string) $challenge->get('difficulty_tier');
        } else {
            // Fallback: deterministic random selection seeded by date
            $tier = \App\Domain\Games\GameDifficulty::dailyTier($dayOfWeek);
            $direction = $dayOfWeek % 2 === 0 ? 'english_to_ojibwe' : 'ojibwe_to_english';
            $entryId = $this->selectRandomWord($tier, $today);
            if ($entryId === null) {
                return $this->json(['error' => 'No words available for today'], 503);
            }
        }

        $entry = $this->entityTypeManager->getRepository('dictionary_entry')->find((string) $entryId);
        if ($entry === null) {
            return $this->json(['error' => 'Word not found'], 503);
        }

        $word = (string) $entry->get('word');

        // Create server-side session for validation
        $sessionRepository = $this->entityTypeManager->getRepository('game_session');
        $session = $sessionRepository->create([
            'game_type' => 'shkoda',
            'mode' => 'daily',
            'direction' => $direction,
            'dictionary_entry_id' => $entryId,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'daily_date' => $today,
            'difficulty_tier' => $tier,
        ]);
        $sessionRepository->save($session);

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
    public function word(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
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

        $entry = $this->entityTypeManager->getRepository('dictionary_entry')->find((string) $entryId);
        if ($entry === null) {
            return $this->json(['error' => 'Word not found'], 503);
        }

        $direction = ($query['direction'] ?? 'english_to_ojibwe');
        if (!in_array($direction, ['english_to_ojibwe', 'ojibwe_to_english'], true)) {
            $direction = 'english_to_ojibwe';
        }

        $word = (string) $entry->get('word');

        // For practice/streak, include word (base64 obfuscated, client-side validation)
        $sessionRepository = $this->entityTypeManager->getRepository('game_session');
        $session = $sessionRepository->create([
            'game_type' => 'shkoda',
            'mode' => $mode,
            'direction' => $direction,
            'dictionary_entry_id' => $entryId,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'difficulty_tier' => $tier,
        ]);
        $sessionRepository->save($session);

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
    public function guess(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
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
        $entry = $this->entityTypeManager->getRepository('dictionary_entry')
            ->find((string) (int) $session->get('dictionary_entry_id'));
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

        $sessionRepository = $this->entityTypeManager->getRepository('game_session');
        $session->set('guesses', json_encode($previousGuesses));
        $session->set('wrong_count', $wrongCount);
        $session->set('status', $status);
        $sessionRepository->save($session);

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
    public function complete(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
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

            $sessionRepository = $this->entityTypeManager->getRepository('game_session');
            $session->set('status', $result);
            $session->set('guesses', json_encode($guesses));
            $session->set('wrong_count', $wrongCount);
            $sessionRepository->save($session);
        }

        // Load word data for teaching moment
        $entry = $this->entityTypeManager->getRepository('dictionary_entry')
            ->find((string) (int) $session->get('dictionary_entry_id'));

        if ($entry === null) {
            return $this->json(['error' => 'Word not found'], 500);
        }

        $word = (string) $entry->get('word');
        $slug = (string) $entry->get('slug');

        // Load example sentence if available
        $exampleRepository = $this->entityTypeManager->getRepository('example_sentence');
        $exampleIds = $exampleRepository->getQuery()->setAccount($account)
            ->condition('dictionary_entry_id', $entry->id())
            ->condition('status', 1)
            ->range(0, 1)
            ->execute();
        $example = $exampleIds !== [] ? $exampleRepository->find((string) reset($exampleIds)) : null;

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

    // --- Private helpers ---

    private function selectRandomWord(string $tier, string $seed = ''): ?int
    {
        $repository = $this->entityTypeManager->getRepository('dictionary_entry');

        // Draw from the WHOLE dictionary, not the first 500 (alphabetical "aa…")
        // rows. #793: sample diversely, then keep only learnable words.
        $allIds = $repository->getQuery()->accessCheck(false)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->condition('definition', '%"%', 'LIKE')
            ->execute();

        if ($allIds === []) {
            return null;
        }

        // Daily must be stable for the whole day: seed the sample + the pick so
        // every visitor gets the same word; practice stays freshly random.
        $isDaily = $seed !== '';
        if ($isDaily) {
            mt_srand((int) crc32($seed));
        }
        $sample = LearnableWord::sampleIds($allIds, 800);

        $tierMatched = [];
        $anyLearnable = [];
        foreach ($repository->findMany($sample) as $entry) {
            $word = (string) $entry->get('word');
            $def = $this->cleanDefinition((string) $entry->get('definition'));
            if (!LearnableWord::isLearnable($word, $def)) {
                continue;
            }
            $anyLearnable[] = $entry->id();
            if (ShkodaEngine::difficultyTier($word, (string) $entry->get('part_of_speech')) === $tier) {
                $tierMatched[] = $entry->id();
            }
        }

        $filtered = $tierMatched !== [] ? $tierMatched : $anyLearnable;
        if ($filtered === []) {
            if ($isDaily) {
                mt_srand();
            }
            return null;
        }

        // Deterministic order so the seeded index is stable across requests.
        sort($filtered);

        if ($isDaily) {
            $index = (int) (abs(crc32($seed)) % count($filtered));
            mt_srand();
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
