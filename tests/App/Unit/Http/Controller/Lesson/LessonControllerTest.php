<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller\Lesson;

use App\Http\Controller\Lesson\LessonController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Security-focused tests for the public lesson media endpoint: the kind/id
 * path allowlist (only thumb|audio for the 16 Lesson 1 ids, no traversal).
 * These run before any filesystem access, so they need no corpus directory or
 * database.
 */
#[CoversClass(LessonController::class)]
final class LessonControllerTest extends TestCase
{
    private function controller(): LessonController
    {
        return new LessonController(
            $this->createMock(EntityTypeManager::class),
            new Environment(new ArrayLoader([])),
        );
    }

    private function account(bool $admin): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('hasPermission')->willReturn($admin);

        return $account;
    }

    private function get(array $params, bool $admin): int
    {
        return $this->controller()
            ->media($params, [], $this->account($admin), HttpRequest::create('/'))
            ->getStatusCode();
    }

    #[Test]
    public function mediaRejectsUnknownKind(): void
    {
        self::assertSame(404, $this->get(['kind' => 'evil', 'id' => 'sb-005'], true));
    }

    #[Test]
    public function mediaRejectsIdNotInLesson(): void
    {
        self::assertSame(404, $this->get(['kind' => 'thumb', 'id' => 'sb-999'], true));
    }

    #[Test]
    public function mediaRejectsTraversalId(): void
    {
        self::assertSame(404, $this->get(['kind' => 'thumb', 'id' => '../../../etc/passwd'], true));
    }

    #[Test]
    public function mediaRejectsNonLessonCorpusId(): void
    {
        // sb-002 is a real corpus item but is NOT one of the 16 Lesson 1 cards,
        // so it must not be reachable through the lesson media endpoint.
        self::assertSame(404, $this->get(['kind' => 'audio', 'id' => 'sb-002'], true));
    }
}
