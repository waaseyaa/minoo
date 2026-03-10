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
    public function setAndConsumeRoundTrip(): void
    {
        Flash::set('success', 'Request assigned.');

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
        Flash::set('success', 'Done.');

        Flash::consume();
        $second = Flash::consume();

        self::assertNull($second);
    }

    #[Test]
    public function consumeReturnsNullForEmptyMessage(): void
    {
        $_SESSION['flash'] = ['type' => 'success', 'message' => ''];

        self::assertNull(Flash::consume());
    }

    #[Test]
    public function consumeDefaultsTypeToSuccess(): void
    {
        $_SESSION['flash'] = ['message' => 'Hello'];

        $flash = Flash::consume();

        self::assertNotNull($flash);
        self::assertSame('success', $flash['type']);
    }

    #[Test]
    public function consumeReturnsNullWhenFlashIsNotArray(): void
    {
        $_SESSION['flash'] = 'not-an-array';

        self::assertNull(Flash::consume());
    }
}
