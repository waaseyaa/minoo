<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\CrosswordController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityQueryInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(CrosswordController::class)]
final class CrosswordControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;
    private Environment $twig;
    private GateInterface $gate;
    private EntityStorageInterface $puzzleStorage;
    private EntityQueryInterface $puzzleQuery;
    private AccountInterface $account;
    private HttpRequest $request;

    protected function setUp(): void
    {
        $this->puzzleQuery = $this->createMock(EntityQueryInterface::class);
        $this->puzzleQuery->method('condition')->willReturnSelf();
        $this->puzzleQuery->method('sort')->willReturnSelf();
        $this->puzzleQuery->method('range')->willReturnSelf();

        $this->puzzleStorage = $this->createMock(EntityStorageInterface::class);
        $this->puzzleStorage->method('getQuery')->willReturn($this->puzzleQuery);

        $this->entityTypeManager = $this->createMock(EntityTypeManager::class);
        $this->entityTypeManager->method('getStorage')
            ->willReturnCallback(fn(string $type) => match ($type) {
                'crossword_puzzle' => $this->puzzleStorage,
                default => $this->createMock(EntityStorageInterface::class),
            });

        $this->twig = new Environment(new ArrayLoader([
            'pages/games/crossword.html.twig' => '{{ path }}',
        ]));

        $this->gate = $this->createMock(GateInterface::class);
        $this->account = $this->createMock(AccountInterface::class);
        $this->request = HttpRequest::create('/');
    }

    #[Test]
    public function random_returns_error_when_no_puzzles_for_tier(): void
    {
        $this->puzzleQuery->method('execute')->willReturn([]);

        $controller = new CrosswordController($this->entityTypeManager, $this->twig, $this->gate);
        $response = $controller->random([], ['tier' => 'medium'], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertSame('no_puzzles', $content['error']);
        $this->assertSame('medium', $content['tier']);
    }

    #[Test]
    public function random_defaults_invalid_tier_to_easy(): void
    {
        $this->puzzleQuery->method('execute')->willReturn([]);

        $controller = new CrosswordController($this->entityTypeManager, $this->twig, $this->gate);
        $response = $controller->random([], ['tier' => 'extreme'], $this->account, $this->request);

        $this->assertSame(200, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertSame('easy', $content['tier']);
    }
}
