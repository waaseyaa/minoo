<?php

declare(strict_types=1);

namespace App\Tests\Unit\Feed;

use App\Feed\FeedContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedContext::class)]
final class FeedContextTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $ctx = FeedContext::defaults();

        $this->assertNull($ctx->latitude);
        $this->assertNull($ctx->longitude);
        $this->assertSame('all', $ctx->activeFilter);
        $this->assertSame([], $ctx->requestedTypes);
        $this->assertNull($ctx->cursor);
        $this->assertSame(20, $ctx->limit);
        $this->assertFalse($ctx->isFirstVisit);
        $this->assertFalse($ctx->isAuthenticated);
    }

    #[Test]
    public function it_creates_with_location(): void
    {
        $ctx = new FeedContext(
            latitude: 46.5,
            longitude: -81.2,
            activeFilter: 'event',
            requestedTypes: ['event', 'group'],
            cursor: 'abc123',
            limit: 10,
            isFirstVisit: true,
            isAuthenticated: false,
        );

        $this->assertSame(46.5, $ctx->latitude);
        $this->assertSame(-81.2, $ctx->longitude);
        $this->assertSame('event', $ctx->activeFilter);
        $this->assertSame(['event', 'group'], $ctx->requestedTypes);
        $this->assertSame('abc123', $ctx->cursor);
        $this->assertSame(10, $ctx->limit);
        $this->assertTrue($ctx->isFirstVisit);
        $this->assertFalse($ctx->isAuthenticated);
    }

    #[Test]
    public function it_reports_has_location(): void
    {
        $with = new FeedContext(latitude: 46.5, longitude: -81.2);
        $without = FeedContext::defaults();

        $this->assertTrue($with->hasLocation());
        $this->assertFalse($without->hasLocation());
    }
}
