<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Geo;

use Minoo\Geo\GeoDistance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GeoDistance::class)]
final class GeoDistanceTest extends TestCase
{
    #[Test]
    public function same_point_returns_zero(): void
    {
        $this->assertSame(0.0, GeoDistance::haversine(46.15, -81.72, 46.15, -81.72));
    }

    #[Test]
    public function sagamok_to_sudbury_is_about_68_km(): void
    {
        $distance = GeoDistance::haversine(46.15, -81.72, 46.49, -80.99);
        $this->assertEqualsWithDelta(68.0, $distance, 5.0);
    }

    #[Test]
    public function sudbury_to_sault_ste_marie_is_about_262_km(): void
    {
        $distance = GeoDistance::haversine(46.49, -80.99, 46.52, -84.35);
        $this->assertEqualsWithDelta(257.0, $distance, 10.0);
    }

    #[Test]
    public function order_of_arguments_does_not_matter(): void
    {
        $forward = GeoDistance::haversine(46.15, -81.72, 46.49, -80.99);
        $reverse = GeoDistance::haversine(46.49, -80.99, 46.15, -81.72);
        $this->assertSame($forward, $reverse);
    }
}
