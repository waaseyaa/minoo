# Agim Number Game Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the Agim counting game — players see an Arabic numeral and type the Ojibwe word — with 4 progressive levels covering 1–19.

**Architecture:** New `AgimController` handles 5 API endpoints plus a page render. Session state lives in the existing `GameSession` entity (`game_type=agim`). Number-to-word mapping is a private constant in the controller drawn from existing `dictionary_entry` rows. Frontend is a vanilla-JS page that drives the game loop via fetch.

**Tech Stack:** PHP 8.4, PHPUnit 10.5, Twig 3, vanilla JS (no build step).

---

## File Map

| Action | File | Responsibility |
|---|---|---|
| Create | `src/Controller/AgimController.php` | start, prompt, answer, complete, page, stats |
| Create | `templates/agim.html.twig` | Game page scaffold |
| Create | `public/js/agim.js` | Client-side game loop |
| Create | `tests/Minoo/Unit/Controller/AgimControllerTest.php` | Unit tests |
| Modify | `src/Provider/GameServiceProvider.php` | Register 6 Agim routes |
| Modify | `templates/games.html.twig` | Add Agim game card |
| Modify | `resources/lang/en.php` | Add Agim translation strings |

---

### Task 1: Add Agim routes to GameServiceProvider

**Files:**
- Modify: `src/Provider/GameServiceProvider.php`

- [ ] **Step 1: Add 6 Agim routes at the end of `routes()` in `GameServiceProvider.php`**

Open `src/Provider/GameServiceProvider.php`. At the end of the `routes()` method, just before the closing `}`, add:

```php
        // --- Agim routes ---

        $router->addRoute(
            'games.agim',
            RouteBuilder::create('/games/agim')
                ->controller('Minoo\\Controller\\AgimController::page')
                ->allowAll()
                ->render()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.agim.start',
            RouteBuilder::create('/api/games/agim/start')
                ->controller('Minoo\\Controller\\AgimController::start')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.agim.prompt',
            RouteBuilder::create('/api/games/agim/prompt')
                ->controller('Minoo\\Controller\\AgimController::prompt')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'api.games.agim.answer',
            RouteBuilder::create('/api/games/agim/answer')
                ->controller('Minoo\\Controller\\AgimController::answer')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.agim.complete',
            RouteBuilder::create('/api/games/agim/complete')
                ->controller('Minoo\\Controller\\AgimController::complete')
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        $router->addRoute(
            'api.games.agim.stats',
            RouteBuilder::create('/api/games/agim/stats')
                ->controller('Minoo\\Controller\\AgimController::stats')
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );
```

- [ ] **Step 2: Register AgimController in `composer.json` autoload (verify it will be found)**

The `Minoo\\Controller\\` namespace is already registered under PSR-4 in `composer.json`. No change needed — confirm by checking:

```bash
grep -A2 '"Minoo\\\\"' composer.json
```

Expected: `"src/"` as the path for `Minoo\\`.

- [ ] **Step 3: Run existing tests to verify no regressions**

```bash
cd /home/jones/dev/minoo && ./vendor/bin/phpunit --testsuite MinooUnit 2>&1 | tail -5
```

Expected: `OK (N tests, N assertions)` — same count as before.

- [ ] **Step 4: Commit**

```bash
cd /home/jones/dev/minoo
git add src/Provider/GameServiceProvider.php
git commit -m "feat(#XXX): register Agim game routes"
```

Replace `XXX` with the GitHub issue number for this feature.

---

### Task 2: Implement AgimController with unit tests (TDD)

**Files:**
- Create: `tests/Minoo/Unit/Controller/AgimControllerTest.php`
- Create: `src/Controller/AgimController.php`

- [ ] **Step 1: Write the failing test file**

Create `tests/Minoo/Unit/Controller/AgimControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\AgimController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(AgimController::class)]
final class AgimControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private GateInterface $gate;
    private EntityStorageInterface $sessionStorage;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->sessionStorage = $this->createMock(EntityStorageInterface::class);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')
            ->willReturnCallback(fn(string $type) => match ($type) {
                'game_session' => $this->sessionStorage,
                default => $this->createMock(EntityStorageInterface::class),
            });

        $this->twig = new Environment(new ArrayLoader([
            'agim.html.twig' => '{{ path }}',
        ]));

        $this->gate = $this->createMock(GateInterface::class);
        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    /** Build a mock ContentEntityBase with pre-set field values. */
    private function makeSession(array $fields): ContentEntityBase
    {
        $mock = $this->createMock(ContentEntityBase::class);
        $captured = $fields;
        $mock->method('get')->willReturnCallback(fn(string $f) => $captured[$f] ?? null);
        $mock->method('set')->willReturnCallback(function (string $f, mixed $v) use ($mock, &$captured) {
            $captured[$f] = $v;
            return $mock;
        });
        return $mock;
    }

    #[Test]
    public function start_creates_session_for_level_1(): void
    {
        $session = $this->makeSession(['uuid' => 'abc-123']);
        $this->sessionStorage->method('create')->willReturn($session);
        $this->account->method('isAuthenticated')->willReturn(false);

        $controller = new AgimController($this->entityTypeManager, $this->twig, $this->gate);
        $response = $controller->start([], ['level' => '1'], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $body = json_decode($response->content, true);
        $this->assertSame('abc-123', $body['session_token']);
        $this->assertSame(1, $body['level']);
        $this->assertSame(5, $body['total']);
        $this->assertContains($body['numeral'], [1, 2, 3, 4, 5]);
    }

    #[Test]
    public function start_clamps_invalid_level_to_1(): void
    {
        $session = $this->makeSession(['uuid' => 'abc-456']);
        $this->sessionStorage->method('create')->willReturn($session);
        $this->account->method('isAuthenticated')->willReturn(false);

        $controller = new AgimController($this->entityTypeManager, $this->twig, $this->gate);
        $response = $controller->start([], ['level' => '99'], $this->account, $this->request);

        $body = json_decode($response->content, true);
        $this->assertSame(1, $body['level']);
        $this->assertSame(5, $body['total']);
    }

    #[Test]
    public function start_level_4_has_19_numerals_and_streak_tier(): void
    {
        $createdValues = [];
        $session = $this->makeSession(['uuid' => 'abc-789']);
        $this->sessionStorage->method('create')->willReturnCallback(
            function (array $vals) use ($session, &$createdValues) {
                $createdValues = $vals;
                return $session;
            },
        );
        $this->account->method('isAuthenticated')->willReturn(false);

        $controller = new AgimController($this->entityTypeManager, $this->twig, $this->gate);
        $response = $controller->start([], ['level' => '4'], $this->account, $this->request);

        $body = json_decode($response->content, true);
        $this->assertSame(19, $body['total']);
        $this->assertSame(4, $body['level']);
        $this->assertSame('streak', $createdValues['difficulty_tier']);
        $this->assertSame('agim', $createdValues['game_type']);
    }

    #[Test]
    public function prompt_returns_current_numeral_and_remaining(): void
    {
        $guesses = json_encode(['queue' => [3, 1, 4], 'completed' => [2, 5]]);
        $session = $this->makeSession(['guesses' => $guesses, 'status' => 'in_progress']);
        $this->sessionStorage->method('loadByKey')->willReturn($session);

        $controller = new AgimController($this->entityTypeManager, $this->twig, $this->gate);
        $response = $controller->prompt([], ['session_token' => 'tok-1'], $this->account, $this->request);

        $this->assertSame(200, $response->statusCode);
        $body = json_decode($response->content, true);
        $this->assertSame(3, $body['numeral']);
        $this->assertSame(3, $body['remaining']);
    }

    #[Test]
    public function prompt_returns_404_for_unknown_token(): void
    {
        $this->sessionStorage->method('loadByKey')->willReturn(null);

        $controller = new AgimController($this->entityTypeManager, $this->twig, $this->gate);
        $response = $controller->prompt([], ['session_token' => 'bad-token'], $this->account, $this->request);

        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function answer_correct_removes_numeral_from_queue(): void
    {
        $guesses = json_encode(['queue' => [1, 2, 3], 'completed' => []]);
        $session = $this->makeSession(['guesses' => $guesses, 'status' => 'in_progress']);
        $this->sessionStorage->method('loadByKey')->willReturn($session);
        $this->gate->method('denies')->willReturn(false);

        $controller = new AgimController($this->entityTypeManager, $this->twig, $this->gate);
        $request = $this->jsonPost(['session_token' => 'tok-1', 'numeral' => 1, 'answer' => 'bezhig']);
        $response = $controller->answer([], [], $this->account, $request);

        $this->assertSame(200, $response->statusCode);
        $body = json_decode($response->content, true);
        $this->assertTrue($body['correct']);
        $this->assertSame('bezhig', $body['expected_word']);
        $this->assertSame(2, $body['remaining']);
    }

    #[Test]
    public function answer_incorrect_requeues_numeral_at_end(): void
    {
        $guesses = json_encode(['queue' => [1, 2, 3], 'completed' => []]);
        $session = $this->makeSession(['guesses' => $guesses, 'status' => 'in_progress']);
        $this->sessionStorage->method('loadByKey')->willReturn($session);
        $this->gate->method('denies')->willReturn(false);

        $controller = new AgimController($this->entityTypeManager, $this->twig, $this->gate);
        $request = $this->jsonPost(['session_token' => 'tok-1', 'numeral' => 1, 'answer' => 'wrong']);
        $response = $controller->answer([], [], $this->account, $request);

        $body = json_decode($response->content, true);
        $this->assertFalse($body['correct']);
        $this->assertSame(3, $body['remaining']); // still 3 — numeral re-queued
        $this->assertSame('bezhig', $body['expected_word']);
    }

    #[Test]
    public function answer_is_case_insensitive(): void
    {
        $guesses = json_encode(['queue' => [2], 'completed' => []]);
        $session = $this->makeSession(['guesses' => $guesses, 'status' => 'in_progress']);
        $this->sessionStorage->method('loadByKey')->willReturn($session);
        $this->gate->method('denies')->willReturn(false);

        $controller = new AgimController($this->entityTypeManager, $this->twig, $this->gate);
        $request = $this->jsonPost(['session_token' => 'tok-1', 'numeral' => 2, 'answer' => 'NIIZH']);
        $response = $controller->answer([], [], $this->account, $request);

        $body = json_decode($response->content, true);
        $this->assertTrue($body['correct']);
    }

    #[Test]
    public function answer_completes_session_when_last_numeral_correct(): void
    {
        $guesses = json_encode(['queue' => [5], 'completed' => [1, 2, 3, 4]]);
        $setValues = [];
        $session = $this->createMock(ContentEntityBase::class);
        $session->method('get')->willReturnCallback(fn(string $f) => match ($f) {
            'guesses' => $guesses,
            'status' => 'in_progress',
            default => null,
        });
        $session->method('set')->willReturnCallback(function (string $f, mixed $v) use ($session, &$setValues) {
            $setValues[$f] = $v;
            return $session;
        });
        $this->sessionStorage->method('loadByKey')->willReturn($session);
        $this->gate->method('denies')->willReturn(false);

        $controller = new AgimController($this->entityTypeManager, $this->twig, $this->gate);
        $request = $this->jsonPost(['session_token' => 'tok-1', 'numeral' => 5, 'answer' => 'naanan']);
        $response = $controller->answer([], [], $this->account, $request);

        $body = json_decode($response->content, true);
        $this->assertTrue($body['correct']);
        $this->assertSame(0, $body['remaining']);
        $this->assertSame('completed', $setValues['status']);
    }

    /** Build a POST request with a JSON body. */
    private function jsonPost(array $data): HttpRequest
    {
        $request = HttpRequest::create('/', 'POST', [], [], [], [], json_encode($data));
        $request->headers->set('Content-Type', 'application/json');
        return $request;
    }
}
```

- [ ] **Step 2: Run to confirm it fails (class not found)**

```bash
cd /home/jones/dev/minoo && ./vendor/bin/phpunit tests/Minoo/Unit/Controller/AgimControllerTest.php 2>&1 | head -10
```

Expected: `Error: Class "Minoo\Controller\AgimController" not found`

- [ ] **Step 3: Create the controller**

Create `src/Controller/AgimController.php`:

```php
<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\GameStatsCalculator;
use Minoo\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

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

    /** Render the Agim game page. */
    public function page(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('agim.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/games/agim',
        ]));
        return new SsrResponse(content: $html);
    }

    /**
     * GET /api/games/agim/start?level={1-4}
     * Creates a new game session for the requested level.
     */
    public function start(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $level = max(1, min(4, (int) ($query['level'] ?? 1)));
        $tier = self::TIERS[$level];
        [$first, $last] = self::LEVEL_RANGES[$level];
        $numerals = range($first, $last);
        shuffle($numerals);

        $storage = $this->entityTypeManager->getStorage('game_session');
        $session = $storage->create([
            'game_type' => 'agim',
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
    public function prompt(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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
    public function answer(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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

        $stats = GameStatsCalculator::build($this->entityTypeManager, $account, 'agim', [], ['completed']);
        $timeSeconds = time() - (int) $session->get('created_at');

        return $this->json([
            'completed' => true,
            'time_seconds' => $timeSeconds,
            'teachings' => $teachings,
            'stats' => $stats,
        ]);
    }

    /** GET /api/games/agim/stats — auth required (enforced at route level). */
    public function stats(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        return $this->json(
            GameStatsCalculator::build($this->entityTypeManager, $account, 'agim', [], ['completed']),
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
```

- [ ] **Step 4: Run tests**

```bash
cd /home/jones/dev/minoo && ./vendor/bin/phpunit tests/Minoo/Unit/Controller/AgimControllerTest.php -v 2>&1 | tail -20
```

Expected: `OK (9 tests, N assertions)`

- [ ] **Step 5: Run full unit suite to check for regressions**

```bash
cd /home/jones/dev/minoo && ./vendor/bin/phpunit --testsuite MinooUnit 2>&1 | tail -5
```

Expected: same test count + 9, all passing.

- [ ] **Step 6: Commit**

```bash
cd /home/jones/dev/minoo
git add src/Controller/AgimController.php tests/Minoo/Unit/Controller/AgimControllerTest.php
git commit -m "feat(#XXX): add AgimController with unit tests"
```

---

### Task 3: Create agim.html.twig and public/js/agim.js

**Files:**
- Create: `templates/agim.html.twig`
- Create: `public/js/agim.js`

- [ ] **Step 1: Create the game page template**

Create `templates/agim.html.twig`:

```twig
{% extends "base.html.twig" %}

{% block title %}Agim — Number Game{% endblock %}

{% block content %}
<div class="agim" id="agim-game" data-api-base="/api/games/agim">
    <div class="game-header">
        <div class="game-header__title">
            <nav class="game-header__breadcrumb" aria-label="Breadcrumb">
                <a href="/games">Games</a> /
            </nav>
            <h1>Agim</h1>
        </div>
        <nav class="game-header__tabs" role="tablist">
            <button class="agim__tab agim__tab--active" role="tab" data-level="1" aria-selected="true">1–5</button>
            <button class="agim__tab" role="tab" data-level="2" aria-selected="false">1–10</button>
            <button class="agim__tab" role="tab" data-level="3" aria-selected="false">1–15</button>
            <button class="agim__tab" role="tab" data-level="4" aria-selected="false">1–19</button>
        </nav>
    </div>

    <div class="agim__progress" id="agim-progress" hidden>
        <div class="agim__progress-bar">
            <div class="agim__progress-fill" id="agim-progress-fill" style="width: 0%"></div>
        </div>
        <span class="agim__progress-label" id="agim-progress-label"></span>
    </div>

    <div class="agim__numeral" id="agim-numeral" aria-live="polite"></div>

    <form class="agim__input-form" id="agim-form" autocomplete="off">
        <label class="visually-hidden" for="agim-answer">Type the Ojibwe word</label>
        <input
            class="agim__input"
            id="agim-answer"
            type="text"
            placeholder="Type the Ojibwe word…"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
        />
        <button class="agim__submit" type="submit">Check</button>
    </form>

    <div class="agim__feedback" id="agim-feedback" aria-live="polite" hidden></div>

    <div class="agim__reveal" id="agim-reveal" hidden>
        <h2 class="agim__reveal-title">Miigwech!</h2>
        <p class="agim__reveal-summary" id="agim-reveal-summary"></p>
        <ul class="agim__teachings" id="agim-teachings"></ul>
        <button class="agim__play-again" id="agim-play-again" type="button">Play again</button>
    </div>

    <div class="agim__loading" id="agim-loading">Loading…</div>

    <div class="visually-hidden" aria-live="polite" id="agim-announcer"></div>
</div>

<script src="/js/agim.js" defer></script>
{% endblock %}
```

- [ ] **Step 2: Create the game JS**

Create `public/js/agim.js`:

```js
(function () {
  'use strict';

  const root = document.getElementById('agim-game');
  if (!root) return;

  const apiBase = root.dataset.apiBase;
  const els = {
    tabs:         root.querySelectorAll('.agim__tab'),
    numeral:      root.getElementById('agim-numeral'),
    form:         root.getElementById('agim-form'),
    input:        root.getElementById('agim-answer'),
    feedback:     root.getElementById('agim-feedback'),
    progress:     root.getElementById('agim-progress'),
    progressFill: root.getElementById('agim-progress-fill'),
    progressLabel:root.getElementById('agim-progress-label'),
    reveal:       root.getElementById('agim-reveal'),
    revealSummary:root.getElementById('agim-reveal-summary'),
    teachings:    root.getElementById('agim-teachings'),
    playAgain:    root.getElementById('agim-play-again'),
    loading:      root.getElementById('agim-loading'),
    announcer:    root.getElementById('agim-announcer'),
  };

  let state = {
    sessionToken: null,
    level: 1,
    total: 0,
    remaining: 0,
    currentNumeral: null,
  };

  // --- Helpers ---

  async function post(path, body) {
    const res = await fetch(apiBase + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    return res.json();
  }

  async function get(path, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(apiBase + path + (qs ? '?' + qs : ''));
    return res.json();
  }

  function show(el) { el.hidden = false; }
  function hide(el) { el.hidden = true; }

  function announce(msg) {
    els.announcer.textContent = '';
    requestAnimationFrame(() => { els.announcer.textContent = msg; });
  }

  function updateProgress() {
    const done = state.total - state.remaining;
    const pct = state.total > 0 ? Math.round((done / state.total) * 100) : 0;
    els.progressFill.style.width = pct + '%';
    els.progressLabel.textContent = done + ' / ' + state.total;
  }

  function showFeedback(correct, expectedWord) {
    els.feedback.className = 'agim__feedback agim__feedback--' + (correct ? 'correct' : 'wrong');
    els.feedback.textContent = correct
      ? '✓ ' + expectedWord
      : '✗ ' + expectedWord + ' — try again later';
    show(els.feedback);
    setTimeout(() => hide(els.feedback), 1800);
  }

  // --- Game flow ---

  async function startGame(level) {
    hide(els.reveal);
    hide(els.feedback);
    hide(els.progress);
    els.numeral.textContent = '';
    els.input.value = '';
    show(els.loading);

    const data = await get('/start', { level });
    hide(els.loading);

    if (data.error) { alert('Could not start game: ' + data.error); return; }

    state.sessionToken = data.session_token;
    state.level = data.level;
    state.total = data.total;
    state.remaining = data.total;
    state.currentNumeral = data.numeral;

    show(els.progress);
    updateProgress();
    showNumeral(data.numeral);
    els.input.focus();
  }

  function showNumeral(n) {
    els.numeral.textContent = n;
    announce('What is ' + n + ' in Ojibwe?');
  }

  async function submitAnswer(answer) {
    const data = await post('/answer', {
      session_token: state.sessionToken,
      numeral: state.currentNumeral,
      answer,
    });

    state.remaining = data.remaining;
    updateProgress();
    showFeedback(data.correct, data.expected_word);
    els.input.value = '';

    if (data.remaining === 0) {
      await finishGame();
      return;
    }

    // Load next numeral
    const prompt = await get('/prompt', { session_token: state.sessionToken });
    if (prompt.numeral) {
      state.currentNumeral = prompt.numeral;
      showNumeral(prompt.numeral);
    }
    els.input.focus();
  }

  async function finishGame() {
    const data = await post('/complete', { session_token: state.sessionToken });

    hide(els.numeral);
    hide(els.form);

    const mins = Math.floor((data.time_seconds || 0) / 60);
    const secs = (data.time_seconds || 0) % 60;
    els.revealSummary.textContent =
      'Finished in ' + (mins > 0 ? mins + 'm ' : '') + secs + 's';

    els.teachings.innerHTML = '';
    for (const t of (data.teachings || [])) {
      const li = document.createElement('li');
      li.className = 'agim__teaching-item';
      li.innerHTML =
        '<span class="agim__teaching-numeral">' + t.numeral + '</span>' +
        ' <span class="agim__teaching-word">' + t.word + '</span>' +
        (t.meaning ? ' — <span class="agim__teaching-meaning">' + t.meaning + '</span>' : '');
      els.teachings.appendChild(li);
    }

    show(els.reveal);
    announce('Miigwech! You completed level ' + state.level);
  }

  // --- Events ---

  els.tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      els.tabs.forEach(t => { t.classList.remove('agim__tab--active'); t.setAttribute('aria-selected', 'false'); });
      tab.classList.add('agim__tab--active');
      tab.setAttribute('aria-selected', 'true');
      state.level = parseInt(tab.dataset.level, 10);
      show(els.numeral);
      show(els.form);
      startGame(state.level);
    });
  });

  els.form.addEventListener('submit', e => {
    e.preventDefault();
    const answer = els.input.value.trim();
    if (answer === '') return;
    submitAnswer(answer);
  });

  els.playAgain.addEventListener('click', () => {
    show(els.numeral);
    show(els.form);
    startGame(state.level);
  });

  // Boot
  startGame(1);
})();
```

- [ ] **Step 3: Verify the template renders (manual check)**

```bash
cd /home/jones/dev/minoo && php -S localhost:8081 -t public &
sleep 1 && curl -s http://localhost:8081/games/agim | grep '<h1>' && kill %1
```

Expected: `<h1>Agim</h1>` in the output.

- [ ] **Step 4: Commit**

```bash
cd /home/jones/dev/minoo
git add templates/agim.html.twig public/js/agim.js
git commit -m "feat(#XXX): add Agim game template and JS"
```

---

### Task 4: Add Agim card to games hub and translation strings

**Files:**
- Modify: `templates/games.html.twig`
- Modify: `resources/lang/en.php`

- [ ] **Step 1: Add Agim card to games.html.twig**

In `templates/games.html.twig`, add a new `<a>` card after the Matcher card (before the closing `</section>` of `games-hub__featured`):

```twig
        <a href="/games/agim" class="game-card game-card--featured">
            <div class="game-card__icon">
                <svg viewBox="0 0 80 80" width="80" height="80" aria-hidden="true">
                    <text x="10" y="38" font-size="28" font-family="monospace" fill="currentColor" opacity="0.9">1</text>
                    <text x="32" y="52" font-size="22" font-family="monospace" fill="currentColor" opacity="0.7">2</text>
                    <text x="52" y="40" font-size="20" font-family="monospace" fill="currentColor" opacity="0.6">3</text>
                    <text x="18" y="68" font-size="16" font-family="monospace" fill="currentColor" opacity="0.5">4</text>
                    <text x="56" y="65" font-size="14" font-family="monospace" fill="currentColor" opacity="0.4">5</text>
                </svg>
            </div>
            <div class="game-card__content">
                <span class="game-card__badge">{{ trans('games.number_game_badge') }}</span>
                <h3 class="game-card__title">{{ trans('games.agim_title') }}</h3>
                <p class="game-card__description">{{ trans('games.agim_description') }}</p>
                <span class="game-card__cta">{{ trans('games.play_now') }}</span>
            </div>
        </a>
```

- [ ] **Step 2: Add translation strings to resources/lang/en.php**

Find the games section in `resources/lang/en.php` and add:

```php
    'games.agim_title'       => 'Agim',
    'games.agim_description' => 'Learn to count in Nishnaabemwin. Type the Ojibwe word for each number.',
    'games.number_game_badge'=> 'Number Game',
```

- [ ] **Step 3: Run full test suite**

```bash
cd /home/jones/dev/minoo && ./vendor/bin/phpunit 2>&1 | tail -5
```

Expected: all tests passing, count same as before + 9 new.

- [ ] **Step 4: Commit**

```bash
cd /home/jones/dev/minoo
git add templates/games.html.twig resources/lang/en.php
git commit -m "feat(#XXX): add Agim card to games hub"
```

---

## Spec Coverage Check

| Spec requirement | Task |
|---|---|
| start endpoint — creates session, returns token + numeral | Task 2 |
| prompt endpoint — returns next numeral from queue | Task 2 |
| answer endpoint — correct removes from queue, incorrect re-queues | Task 2 |
| answer endpoint — marks session completed when queue empty | Task 2 |
| complete endpoint — returns teachings + stats | Task 2 |
| stats endpoint — auth required at route level | Task 1 |
| diacritic-tolerant comparison | Task 2 (`normalizeWord`) |
| Level 1–4 range (1–5, 1–10, 1–15, 1–19) | Task 2 |
| difficulty_tier mapped to easy/medium/hard/streak | Task 2 |
| GameSession reuse (`game_type=agim`) | Task 2 |
| NUMBERS constant with all 19 deids | Task 2 |
| agim.html.twig game page | Task 3 |
| public/js/agim.js client game loop | Task 3 |
| Agim card in games hub | Task 4 |
| Routes registered in GameServiceProvider | Task 1 |
