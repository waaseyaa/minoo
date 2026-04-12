<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Geo;

use App\Domain\Geo\ValueObject\LocationContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocationContext::class)]
final class LocationContextTest extends TestCase
{
    #[Test]
    public function fromArrayWithValidDataCreatesContext(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => 42,
            'communityName' => 'Thunder Bay',
            'latitude' => 48.38,
            'longitude' => -89.25,
            'source' => 'manual',
        ]);

        self::assertTrue($ctx->hasLocation());
        self::assertSame(42, $ctx->communityId);
        self::assertSame('Thunder Bay', $ctx->communityName);
        self::assertSame(48.38, $ctx->latitude);
        self::assertSame(-89.25, $ctx->longitude);
    }

    #[Test]
    public function fromArrayWithNonNumericCommunityIdReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => 'test-id',
            'communityName' => 'Fake',
            'latitude' => 48.38,
            'longitude' => -89.25,
            'source' => 'manual',
        ]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function fromArrayWithZeroCommunityIdReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => 0,
            'communityName' => 'Invalid',
            'latitude' => 48.38,
            'longitude' => -89.25,
            'source' => 'manual',
        ]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function fromArrayWithNonNumericLatitudeReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => 42,
            'communityName' => 'Thunder Bay',
            'latitude' => 'invalid',
            'longitude' => -89.25,
            'source' => 'manual',
        ]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function fromArrayWithOutOfRangeLatitudeReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => 42,
            'communityName' => 'Thunder Bay',
            'latitude' => 91.0,
            'longitude' => -89.25,
            'source' => 'manual',
        ]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function fromArrayWithOutOfRangeLongitudeReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => 42,
            'communityName' => 'Thunder Bay',
            'latitude' => 48.38,
            'longitude' => 181.0,
            'source' => 'manual',
        ]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function fromArrayWithMissingCommunityIdReturnsNone(): void
    {
        $ctx = LocationContext::fromArray([
            'communityName' => 'Thunder Bay',
            'latitude' => 48.38,
            'longitude' => -89.25,
        ]);

        self::assertFalse($ctx->hasLocation());
    }

    #[Test]
    public function fromArrayWithStringNumericCommunityIdCastsCorrectly(): void
    {
        $ctx = LocationContext::fromArray([
            'communityId' => '42',
            'communityName' => 'Thunder Bay',
            'latitude' => 48.38,
            'longitude' => -89.25,
            'source' => 'cookie',
        ]);

        self::assertTrue($ctx->hasLocation());
        self::assertSame(42, $ctx->communityId);
    }

    #[Test]
    public function noneHasNoLocation(): void
    {
        $ctx = LocationContext::none();

        self::assertFalse($ctx->hasLocation());
        self::assertNull($ctx->communityId);
    }

    #[Test]
    public function toArrayRoundTrips(): void
    {
        $original = new LocationContext(
            communityId: 42,
            communityName: 'Thunder Bay',
            latitude: 48.38,
            longitude: -89.25,
            source: 'manual',
        );

        $restored = LocationContext::fromArray($original->toArray());

        self::assertSame($original->communityId, $restored->communityId);
        self::assertSame($original->communityName, $restored->communityName);
        self::assertSame($original->latitude, $restored->latitude);
        self::assertSame($original->longitude, $restored->longitude);
        self::assertSame($original->source, $restored->source);
    }
}
