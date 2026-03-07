<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Controller;

use Minoo\Controller\PeopleController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(PeopleController::class)]
final class PeopleControllerTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated(): void
    {
        $entityTypeManager = $this->createMock(EntityTypeManager::class);
        $twig = new Environment(new ArrayLoader([]));

        $controller = new PeopleController($entityTypeManager, $twig);

        $this->assertInstanceOf(PeopleController::class, $controller);
    }
}
