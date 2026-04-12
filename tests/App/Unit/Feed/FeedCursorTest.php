<?php

declare(strict_types=1);

namespace App\Tests\Unit\Feed;

use App\Feed\FeedCursor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedCursor::class)]
final class FeedCursorTest extends TestCase
{
    #[Test]
    public function it_encodes_and_decodes_roundtrip(): void
    {
        $sortKey = '9999:0000000002.30:01:09223372036854775707:event:42';
        $cursor = FeedCursor::encode($sortKey, 'event', 'event:42');
        $decoded = FeedCursor::decode($cursor);

        $this->assertSame($sortKey, $decoded['lastSortKey']);
        $this->assertSame('event', $decoded['lastType']);
        $this->assertSame('event:42', $decoded['lastId']);
    }

    #[Test]
    public function it_returns_null_for_invalid_cursor(): void
    {
        $this->assertNull(FeedCursor::decode('not-valid-base64-json'));
        $this->assertNull(FeedCursor::decode(''));
    }

    #[Test]
    public function it_returns_null_for_missing_fields(): void
    {
        $partial = base64_encode(json_encode(['lastSortKey' => 'x']));
        $this->assertNull(FeedCursor::decode($partial));
    }
}
