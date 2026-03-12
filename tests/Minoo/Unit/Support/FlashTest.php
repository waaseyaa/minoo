<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Support;

use Minoo\Support\Flash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Flash::class)]
final class FlashTest extends TestCase
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
    public function successAndConsumeRoundTrip(): void
    {
        Flash::success('Request assigned.');

        $flash = Flash::consume();

        self::assertNotNull($flash);
        self::assertSame('success', $flash['type']);
        self::assertSame('Request assigned.', $flash['message']);
    }

    #[Test]
    public function consumeReturnsNullWhenNoFlashSet(): void
    {
        self::assertNull(Flash::consume());
    }

    #[Test]
    public function consumeClearsFlashAfterReading(): void
    {
        Flash::success('Done.');

        Flash::consume();
        $second = Flash::consume();

        self::assertNull($second);
    }

    #[Test]
    public function errorSetsErrorType(): void
    {
        Flash::error('Something went wrong.');

        $flash = Flash::consume();

        self::assertNotNull($flash);
        self::assertSame('error', $flash['type']);
    }

    #[Test]
    public function infoSetsInfoType(): void
    {
        Flash::info('For your information.');

        $flash = Flash::consume();

        self::assertNotNull($flash);
        self::assertSame('info', $flash['type']);
    }

    #[Test]
    public function setDelegatesToTypedMethods(): void
    {
        Flash::set('success', 'Hello');

        $flash = Flash::consume();

        self::assertNotNull($flash);
        self::assertSame('success', $flash['type']);
        self::assertSame('Hello', $flash['message']);
    }
}
