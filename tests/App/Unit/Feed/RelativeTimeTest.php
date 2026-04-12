<?php

declare(strict_types=1);

namespace App\Tests\Unit\Feed;

use App\Feed\RelativeTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelativeTime::class)]
final class RelativeTimeTest extends TestCase
{
    private const NOW = 1711000000;

    #[Test]
    public function it_returns_just_now_for_recent(): void
    {
        $this->assertSame('just now', RelativeTime::format(self::NOW, self::NOW));
        $this->assertSame('just now', RelativeTime::format(self::NOW - 30, self::NOW));
        $this->assertSame('just now', RelativeTime::format(self::NOW - 59, self::NOW));
    }

    #[Test]
    public function it_returns_minutes_ago(): void
    {
        $this->assertSame('1m ago', RelativeTime::format(self::NOW - 60, self::NOW));
        $this->assertSame('5m ago', RelativeTime::format(self::NOW - 300, self::NOW));
        $this->assertSame('59m ago', RelativeTime::format(self::NOW - 3540, self::NOW));
    }

    #[Test]
    public function it_returns_hours_ago(): void
    {
        $this->assertSame('1h ago', RelativeTime::format(self::NOW - 3600, self::NOW));
        $this->assertSame('12h ago', RelativeTime::format(self::NOW - 43200, self::NOW));
        $this->assertSame('23h ago', RelativeTime::format(self::NOW - 82800, self::NOW));
    }

    #[Test]
    public function it_returns_yesterday(): void
    {
        $this->assertSame('Yesterday', RelativeTime::format(self::NOW - 86400, self::NOW));
        $this->assertSame('Yesterday', RelativeTime::format(self::NOW - 100000, self::NOW));
    }

    #[Test]
    public function it_returns_date_for_older(): void
    {
        // 3 days ago
        $result = RelativeTime::format(self::NOW - 259200, self::NOW);
        $this->assertSame(date('M j', self::NOW - 259200), $result);
    }

    #[Test]
    public function it_returns_date_for_future_timestamps(): void
    {
        $future = self::NOW + 3600;
        $result = RelativeTime::format($future, self::NOW);
        $this->assertSame(date('M j', $future), $result);
    }
}
