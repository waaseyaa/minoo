<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat;

use App\Chat\ChatRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChatRateLimiter::class)]
final class ChatRateLimiterTest extends TestCase
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
    public function isAllowedReturnsTrueWhenUnderLimit(): void
    {
        $limiter = new ChatRateLimiter(5);

        $this->assertTrue($limiter->isAllowed());
    }

    #[Test]
    public function isAllowedReturnsFalseWhenAtLimit(): void
    {
        $limiter = new ChatRateLimiter(2);
        $limiter->record();
        $limiter->record();

        $this->assertFalse($limiter->isAllowed());
    }

    #[Test]
    public function recordAddsTimestamp(): void
    {
        $limiter = new ChatRateLimiter(10);
        $limiter->record();

        $this->assertSame(9, $limiter->remainingRequests());
    }

    #[Test]
    public function remainingRequestsReturnsCorrectCount(): void
    {
        $limiter = new ChatRateLimiter(3);

        $this->assertSame(3, $limiter->remainingRequests());

        $limiter->record();
        $this->assertSame(2, $limiter->remainingRequests());

        $limiter->record();
        $this->assertSame(1, $limiter->remainingRequests());

        $limiter->record();
        $this->assertSame(0, $limiter->remainingRequests());
    }

    #[Test]
    public function expiredTimestampsArePruned(): void
    {
        $limiter = new ChatRateLimiter(2);

        // Inject old timestamps directly
        $_SESSION['minoo_chat_requests'] = [time() - 120, time() - 90];

        $this->assertTrue($limiter->isAllowed());
        $this->assertSame(2, $limiter->remainingRequests());
    }

    #[Test]
    public function remainingNeverGoesNegative(): void
    {
        $limiter = new ChatRateLimiter(1);
        $limiter->record();
        $limiter->record();

        $this->assertSame(0, $limiter->remainingRequests());
    }
}
