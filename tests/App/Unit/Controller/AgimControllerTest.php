<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\AgimController;
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
use Waaseyaa\Entity\Storage\EntityQueryInterface;
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

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
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

        $body = json_decode($response->getContent(), true);
        $this->assertSame(1, $body['level']);
        $this->assertSame(5, $body['total']);
    }

    #[Test]
    public function start_sets_practice_mode_on_session(): void
    {
        $createdValues = [];
        $session = $this->makeSession(['uuid' => 'abc-mode']);
        $this->sessionStorage->method('create')->willReturnCallback(
            function (array $vals) use ($session, &$createdValues) {
                $createdValues = $vals;
                return $session;
            },
        );
        $this->account->method('isAuthenticated')->willReturn(false);

        $controller = new AgimController($this->entityTypeManager, $this->twig, $this->gate);
        $controller->start([], ['level' => '1'], $this->account, $this->request);

        $this->assertSame('practice', $createdValues['mode'] ?? null);
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

        $body = json_decode($response->getContent(), true);
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

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame(3, $body['numeral']);
        $this->assertSame(3, $body['remaining']);
    }

    #[Test]
    public function prompt_returns_404_for_unknown_token(): void
    {
        $this->sessionStorage->method('loadByKey')->willReturn(null);

        $controller = new AgimController($this->entityTypeManager, $this->twig, $this->gate);
        $response = $controller->prompt([], ['session_token' => 'bad-token'], $this->account, $this->request);

        $this->assertSame(404, $response->getStatusCode());
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

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
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

        $body = json_decode($response->getContent(), true);
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

        $body = json_decode($response->getContent(), true);
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

        $body = json_decode($response->getContent(), true);
        $this->assertTrue($body['correct']);
        $this->assertSame(0, $body['remaining']);
        $this->assertSame('completed', $setValues['status']);
    }

    #[Test]
    public function stats_abandoned_session_breaks_streak(): void
    {
        // Newest session is abandoned — must break the current streak
        $abandoned = $this->makeSession(['status' => 'abandoned', 'created_at' => '200', 'updated_at' => '300']);
        // Older session is completed — should not count toward current streak once it is broken
        $completed = $this->makeSession(['status' => 'completed', 'created_at' => '100', 'updated_at' => '180']);

        $query = $this->createMock(EntityQueryInterface::class);
        $query->method('condition')->willReturnSelf();
        $query->method('execute')->willReturn([2, 1]);

        $this->sessionStorage->method('getQuery')->willReturn($query);
        $this->sessionStorage->method('loadMultiple')->willReturn([2 => $abandoned, 1 => $completed]);

        $this->account->method('isAuthenticated')->willReturn(true);
        $this->account->method('id')->willReturn(42);

        $controller = new AgimController($this->entityTypeManager, $this->twig, $this->gate);
        $response = $controller->stats([], [], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['current_streak']);
    }

    /** Build a POST request with a JSON body. */
    private function jsonPost(array $data): HttpRequest
    {
        $request = HttpRequest::create('/', 'POST', [], [], [], [], json_encode($data));
        $request->headers->set('Content-Type', 'application/json');
        return $request;
    }
}
