<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Support\GeocodingService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GeocodingService::class)]
final class GeocodingServiceTest extends TestCase
{
    #[Test]
    public function parsesValidNominatimResponse(): void
    {
        $json = json_encode([
            ['lat' => '46.5107', 'lon' => '-81.0021', 'display_name' => 'Sagamok'],
        ], JSON_THROW_ON_ERROR);

        $result = GeocodingService::parseResponse($json);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(46.5107, $result['lat'], 0.0001);
        $this->assertEqualsWithDelta(-81.0021, $result['lng'], 0.0001);
    }

    #[Test]
    public function returnsNullForEmptyResponse(): void
    {
        $result = GeocodingService::parseResponse('[]');

        $this->assertNull($result);
    }

    #[Test]
    public function returnsNullForMalformedJson(): void
    {
        $result = GeocodingService::parseResponse('{not valid json');

        $this->assertNull($result);
    }

    #[Test]
    public function returnsNullForMissingCoordinates(): void
    {
        $json = json_encode([
            ['display_name' => 'Somewhere'],
        ], JSON_THROW_ON_ERROR);

        $result = GeocodingService::parseResponse($json);

        $this->assertNull($result);
    }

    #[Test]
    public function buildUrlEncodesAddress(): void
    {
        $url = GeocodingService::buildUrl('123 Main St, Sudbury ON');

        $this->assertStringContainsString('nominatim.openstreetmap.org/search', $url);
        $this->assertStringContainsString('q=123+Main+St', $url);
        $this->assertStringContainsString('format=json', $url);
        $this->assertStringContainsString('limit=1', $url);
    }
}
