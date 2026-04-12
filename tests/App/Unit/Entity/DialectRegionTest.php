<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\DialectRegion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DialectRegion::class)]
final class DialectRegionTest extends TestCase
{
    #[Test]
    public function it_creates_with_code_and_name(): void
    {
        $region = new DialectRegion([
            'code' => 'oji-east',
            'name' => 'Nishnaabemwin',
        ]);

        $this->assertSame('oji-east', $region->id());
        $this->assertSame('Nishnaabemwin', $region->label());
        $this->assertSame('dialect_region', $region->getEntityTypeId());
    }

    #[Test]
    public function it_defaults_optional_fields(): void
    {
        $region = new DialectRegion([
            'code' => 'oji-east',
            'name' => 'Nishnaabemwin',
        ]);

        $values = $region->toArray();
        $this->assertSame('', $values['display_name']);
        $this->assertSame('', $values['language_family']);
        $this->assertSame('', $values['iso_639_3']);
        $this->assertSame([], $values['regions']);
        $this->assertNull($values['boundary_geojson']);
    }

    #[Test]
    public function it_stores_all_fields(): void
    {
        $geojson = '{"type":"Polygon","coordinates":[[[-81.0,46.0],[-80.0,46.0],[-80.0,47.0],[-81.0,47.0],[-81.0,46.0]]]}';
        $region = new DialectRegion([
            'code' => 'oji-east',
            'name' => 'Nishnaabemwin',
            'display_name' => 'Eastern Ojibwe',
            'language_family' => 'algonquian',
            'iso_639_3' => 'ojg',
            'regions' => ['canada:ontario:north-shore-huron', 'canada:ontario:southern'],
            'boundary_geojson' => $geojson,
        ]);

        $values = $region->toArray();
        $this->assertSame('Eastern Ojibwe', $values['display_name']);
        $this->assertSame('algonquian', $values['language_family']);
        $this->assertSame('ojg', $values['iso_639_3']);
        $this->assertSame(['canada:ontario:north-shore-huron', 'canada:ontario:southern'], $values['regions']);
        $this->assertSame($geojson, $values['boundary_geojson']);
    }
}
