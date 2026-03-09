<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Geo;

use Minoo\Geo\LocationContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocationContext::class)]
final class LocationContextTest extends TestCase
{
    #[Test]
    public function has_location_returns_true_when_community_set(): void
    {
        $context = new LocationContext(
            communityId: 1,
            communityName: 'Sagamok',
            latitude: 46.15,
            longitude: -81.77,
            source: 'ip',
        );

        $this->assertTrue($context->hasLocation());
        $this->assertSame(1, $context->communityId);
        $this->assertSame('Sagamok', $context->communityName);
        $this->assertSame('ip', $context->source);
    }

    #[Test]
    public function has_location_returns_false_when_no_community(): void
    {
        $context = LocationContext::none();

        $this->assertFalse($context->hasLocation());
        $this->assertNull($context->communityId);
        $this->assertSame('none', $context->source);
    }

    #[Test]
    public function to_array_returns_serializable_data(): void
    {
        $context = new LocationContext(
            communityId: 1,
            communityName: 'Sagamok',
            latitude: 46.15,
            longitude: -81.77,
            source: 'ip',
        );

        $data = $context->toArray();

        $this->assertSame(1, $data['communityId']);
        $this->assertSame('Sagamok', $data['communityName']);
        $this->assertSame(46.15, $data['latitude']);
        $this->assertSame(-81.77, $data['longitude']);
        $this->assertSame('ip', $data['source']);
    }

    #[Test]
    public function from_array_hydrates_from_session_data(): void
    {
        $context = LocationContext::fromArray([
            'communityId' => 2,
            'communityName' => 'Atikameksheng',
            'latitude' => 46.49,
            'longitude' => -81.05,
            'source' => 'browser',
        ]);

        $this->assertTrue($context->hasLocation());
        $this->assertSame(2, $context->communityId);
        $this->assertSame('browser', $context->source);
    }

    #[Test]
    public function from_array_returns_none_for_empty_array(): void
    {
        $context = LocationContext::fromArray([]);

        $this->assertFalse($context->hasLocation());
    }
}
