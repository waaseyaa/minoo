<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Geo;

use App\Domain\Geo\GeoDistance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GeoDistance::class)]
final class GeoDistanceTest extends TestCase
{
    #[Test]
    public function same_point_is_zero(): void
    {
        $this->assertSame(0.0, GeoDistance::haversine(46.217, -82.067, 46.217, -82.067));
    }

    #[Test]
    public function known_distance_is_accurate(): void
    {
        // Massey (46.217, -82.067) to Sagamok (46.167, -82.217) is ~13 km.
        $km = GeoDistance::haversine(46.217, -82.067, 46.167, -82.217);
        $this->assertGreaterThan(10.0, $km);
        $this->assertLessThan(16.0, $km);
    }

    #[Test]
    public function is_symmetric(): void
    {
        $a = GeoDistance::haversine(46.5, -84.3, 46.2, -81.2);
        $b = GeoDistance::haversine(46.2, -81.2, 46.5, -84.3);
        $this->assertEqualsWithDelta($a, $b, 0.0001);
    }
}
