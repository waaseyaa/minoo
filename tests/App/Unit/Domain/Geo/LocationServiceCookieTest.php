<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Geo;

use App\Domain\Geo\ValueObject\LocationContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for LocationContext::fromArray() resilience when called
 * with data that simulates corrupted cookie/session input.
 *
 * LocationService::fromRequest() depends on Symfony Request and EntityTypeManager,
 * so we test the validation boundary at LocationContext::fromArray() directly.
 */
#[CoversClass(LocationContext::class)]
final class LocationServiceCookieTest extends TestCase
{
    #[Test]
    public function corruptedJsonParsesToInvalidContext(): void
    {
        $data = [
            'communityId' => 'not_a_number',
            'latitude' => 'bad',
            'longitude' => 'worse',
            'source' => 'cookie',
        ];

        $ctx = LocationContext::fromArray($data);

        self::assertFalse($ctx->hasLocation());
        self::assertNull($ctx->communityId);
    }

    #[Test]
    public function emptyArrayReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function validCookieDataCreatesContext(): void
    {
        $data = [
            'communityId' => 42,
            'communityName' => 'Thunder Bay',
            'latitude' => 48.38,
            'longitude' => -89.25,
            'source' => 'manual',
        ];

        $ctx = LocationContext::fromArray($data);

        self::assertTrue($ctx->hasLocation());
        self::assertSame(42, $ctx->communityId);
    }

    #[Test]
    public function negativeCommunityIdReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => -5,
            'communityName' => 'Negative',
            'latitude' => 48.38,
            'longitude' => -89.25,
        ]);

        self::assertFalse($ctx->hasLocation());
    }
}
