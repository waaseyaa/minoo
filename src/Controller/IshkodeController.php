<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Entity\GameSession;
use Minoo\Support\IshkodeEngine;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class IshkodeController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** Render the game page. */
    public function page(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('ishkode.html.twig', [
            'path' => '/games/ishkode',
        ]);

        return new SsrResponse(content: $html);
    }

    /** GET /api/games/ishkode/daily — today's challenge metadata. */
    public function daily(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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
            $tier = IshkodeEngine::dailyTier($dayOfWeek);
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
            'mode' => 'daily',
            'direction' => $direction,
            'dictionary_entry_id' => $entryId,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'daily_date' => $today,
            'difficulty_tier' => $tier,
        ]);
        $sessionStorage->save($session);

        $clue = $direction === 'english_to_ojibwe'
            ? (string) $entry->get('definition')
            : $word;

        return $this->json([
            'session_token' => $session->get('uuid'),
            'word_length' => mb_strlen($word),
            'clue' => $clue,
            'clue_detail' => (string) $entry->get('part_of_speech'),
            'direction' => $direction,
            'difficulty' => $tier,
            'max_wrong' => IshkodeEngine::maxWrongGuesses($tier),
            'date' => $today,
        ]);
    }

    /** GET /api/games/ishkode/word — random word for practice/streak. */
    public function word(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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
            'mode' => $mode,
            'direction' => $direction,
            'dictionary_entry_id' => $entryId,
            'user_id' => $account->isAuthenticated() ? $account->id() : null,
            'difficulty_tier' => $tier,
        ]);
        $sessionStorage->save($session);

        $clue = $direction === 'english_to_ojibwe'
            ? (string) $entry->get('definition')
            : $word;

        return $this->json([
            'session_token' => $session->get('uuid'),
            'word_length' => mb_strlen($word),
            'word_data' => base64_encode($word),
            'clue' => $clue,
            'clue_detail' => (string) $entry->get('part_of_speech'),
            'direction' => $direction,
            'difficulty' => $tier,
            'max_wrong' => IshkodeEngine::maxWrongGuesses($tier),
        ]);
    }

    /** POST /api/games/ishkode/guess — validate a letter (daily mode only). */
    public function guess(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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

        $result = IshkodeEngine::processGuess($word, $letter, $previousGuesses);

        if (!empty($result['already_guessed'])) {
            return $this->json(['error' => 'Letter already guessed', 'already_guessed' => true], 400);
        }

        // Update session
        $previousGuesses[] = mb_strtolower($letter);
        $wrongCount = (int) $session->get('wrong_count');
        if (!$result['correct']) {
            $wrongCount++;
        }

        $maxWrong = IshkodeEngine::maxWrongGuesses((string) $session->get('difficulty_tier'));
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
        $session->set('updated_at', time());
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

    /** POST /api/games/ishkode/complete — submit completed game, get teaching data + stats. */
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

        // For practice/streak, accept client-reported result
        if ($session->get('mode') !== 'daily' && isset($data['result'])) {
            $result = $data['result'] === 'won' ? 'won' : 'lost';
            $guesses = $data['guesses'] ?? [];
            $wrongCount = (int) ($data['wrong_count'] ?? 0);

            $sessionStorage = $this->entityTypeManager->getStorage('game_session');
            $session->set('status', $result);
            $session->set('guesses', json_encode($guesses));
            $session->set('wrong_count', $wrongCount);
            $session->set('updated_at', time());
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
        $stats = $this->buildStats($account);

        return $this->json([
            'word' => $word,
            'definition' => (string) $entry->get('definition'),
            'part_of_speech' => (string) $entry->get('part_of_speech'),
            'stem' => (string) $entry->get('stem'),
            'slug' => $slug,
            'example_ojibwe' => $example !== null ? (string) $example->get('ojibwe_text') : null,
            'example_english' => $example !== null ? (string) $example->get('english_text') : null,
            'stats' => $stats,
        ]);
    }

    /** GET /api/games/ishkode/stats — player stats (auth required). */
    public function stats(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        return $this->json($this->buildStats($account));
    }

    // --- Private helpers ---

    private function selectRandomWord(string $tier, string $seed = ''): ?int
    {
        $storage = $this->entityTypeManager->getStorage('dictionary_entry');
        $query = $storage->getQuery()
            ->condition('status', 1)
            ->condition('consent_public', 1);

        $ids = $query->execute();

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

            $entryTier = IshkodeEngine::difficultyTier($word, $pos);
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

    private function loadSessionByToken(string $uuid): ?GameSession
    {
        $storage = $this->entityTypeManager->getStorage('game_session');
        $ids = $storage->getQuery()
            ->condition('uuid', $uuid)
            ->range(0, 1)
            ->execute();

        if ($ids === []) {
            return null;
        }

        return $storage->load(reset($ids));
    }

    /** @param list<string> $guesses */
    private function isWordFullyRevealed(string $word, array $guesses): bool
    {
        $word = mb_strtolower($word);
        $len = mb_strlen($word);
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($word, $i, 1);
            if (!in_array($char, $guesses, true)) {
                return false;
            }
        }
        return true;
    }

    /** @return array<string, mixed> */
    private function buildStats(AccountInterface $account): array
    {
        if (!$account->isAuthenticated()) {
            return ['authenticated' => false];
        }

        $storage = $this->entityTypeManager->getStorage('game_session');
        $allIds = $storage->getQuery()
            ->condition('user_id', $account->id())
            ->execute();

        if ($allIds === []) {
            return [
                'authenticated' => true,
                'games_played' => 0,
                'win_rate' => 0.0,
                'current_streak' => 0,
                'best_streak' => 0,
            ];
        }

        $sessions = array_values($storage->loadMultiple($allIds));

        // Sort by created_at DESC for streak calculation
        usort($sessions, fn($a, $b) => (int) $b->get('created_at') - (int) $a->get('created_at'));

        $completed = array_filter($sessions, fn($s) => $s->get('status') !== 'in_progress');
        $wins = array_filter($completed, fn($s) => $s->get('status') === 'won');
        $gamesPlayed = count($completed);
        $winRate = $gamesPlayed > 0 ? round(count($wins) / $gamesPlayed, 2) : 0.0;

        // Current streak
        $currentStreak = 0;
        foreach ($sessions as $s) {
            if ($s->get('status') === 'won') {
                $currentStreak++;
            } elseif ($s->get('status') === 'lost') {
                break;
            }
        }

        // Best streak
        $bestStreak = 0;
        $streak = 0;
        foreach ($sessions as $s) {
            if ($s->get('status') === 'won') {
                $streak++;
                $bestStreak = max($bestStreak, $streak);
            } elseif ($s->get('status') === 'lost') {
                $streak = 0;
            }
        }

        return [
            'authenticated' => true,
            'games_played' => $gamesPlayed,
            'win_rate' => $winRate,
            'current_streak' => $currentStreak,
            'best_streak' => $bestStreak,
        ];
    }

    /** @return array<string, mixed> */
    private function jsonBody(HttpRequest $request): array
    {
        $content = $request->getContent();
        if ($content === '' || $content === false) {
            return [];
        }
        try {
            return (array) json_decode((string) $content, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): SsrResponse
    {
        return new SsrResponse(
            content: json_encode($data, JSON_THROW_ON_ERROR),
            statusCode: $status,
            headers: ['Content-Type' => 'application/json'],
        );
    }
}
