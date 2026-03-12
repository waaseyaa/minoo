<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Twig;

use Minoo\Service\FlashMessageService;
use Minoo\Twig\FlashTwigExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlashTwigExtension::class)]
final class FlashTwigExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    #[Test]
    public function registersFunctionNamedFlashMessages(): void
    {
        $ext = new FlashTwigExtension(new FlashMessageService());

        $functions = $ext->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('flash_messages', $functions[0]->getName());
    }

    #[Test]
    public function flashMessagesDelegatesToService(): void
    {
        $service = new FlashMessageService();
        $service->addSuccess('Hello.');

        $ext = new FlashTwigExtension($service);
        $result = $ext->flashMessages();

        self::assertCount(1, $result);
        self::assertSame('success', $result[0]['type']);
        self::assertSame('Hello.', $result[0]['message']);
    }

    #[Test]
    public function flashMessagesConsumesOnCall(): void
    {
        $service = new FlashMessageService();
        $service->addError('Oops.');

        $ext = new FlashTwigExtension($service);
        $ext->flashMessages();
        $second = $ext->flashMessages();

        self::assertSame([], $second);
    }
}
