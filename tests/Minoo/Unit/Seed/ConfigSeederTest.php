<?php

declare(strict_types=1);

namespace Minoo\Tests\Unit\Seed;

use Minoo\Seed\ConfigSeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigSeeder::class)]
final class ConfigSeederTest extends TestCase
{
    #[Test]
    public function it_provides_event_types(): void
    {
        $types = ConfigSeeder::eventTypes();

        $this->assertCount(4, $types);
        $this->assertSame('powwow', $types[0]['type']);
        $this->assertSame('Powwow', $types[0]['name']);
        $this->assertSame('tournament', $types[3]['type']);
    }

    #[Test]
    public function it_provides_group_types(): void
    {
        $types = ConfigSeeder::groupTypes();

        $this->assertCount(4, $types);
        $this->assertSame('online', $types[0]['type']);
    }

    #[Test]
    public function groupTypesIncludesBusiness(): void
    {
        $types = ConfigSeeder::groupTypes();
        $typeIds = array_column($types, 'type');
        $this->assertContains('business', $typeIds);
    }

    #[Test]
    public function businessGroupTypeHasName(): void
    {
        $types = ConfigSeeder::groupTypes();
        $business = null;
        foreach ($types as $type) {
            if ($type['type'] === 'business') {
                $business = $type;
                break;
            }
        }
        $this->assertNotNull($business);
        $this->assertSame('Local Business', $business['name']);
    }

    #[Test]
    public function it_provides_teaching_types(): void
    {
        $types = ConfigSeeder::teachingTypes();

        $this->assertCount(3, $types);
        $this->assertSame('culture', $types[0]['type']);
    }

    #[Test]
    public function it_provides_oral_history_types(): void
    {
        $types = ConfigSeeder::oralHistoryTypes();

        $this->assertCount(5, $types);
        $this->assertSame('creation_story', $types[0]['type']);
        $this->assertSame('Creation Story', $types[0]['name']);
        $this->assertSame('family_story', $types[4]['type']);

        // All entries must have required keys
        foreach ($types as $type) {
            $this->assertArrayHasKey('type', $type);
            $this->assertArrayHasKey('name', $type);
            $this->assertArrayHasKey('description', $type);
        }
    }

    #[Test]
    public function it_provides_dialect_regions(): void
    {
        $regions = ConfigSeeder::dialectRegions();

        $this->assertNotEmpty($regions);

        // First entry should be Eastern Ojibwe (home dialect)
        $this->assertSame('oji-east', $regions[0]['code']);
        $this->assertSame('Nishnaabemwin', $regions[0]['name']);
        $this->assertSame('Eastern Ojibwe', $regions[0]['display_name']);
        $this->assertSame('algonquian', $regions[0]['language_family']);
        $this->assertSame('ojg', $regions[0]['iso_639_3']);
        $this->assertIsArray($regions[0]['regions']);
        $this->assertContains('canada:ontario:north-shore-huron', $regions[0]['regions']);

        // All entries must have required keys
        foreach ($regions as $region) {
            $this->assertArrayHasKey('code', $region);
            $this->assertArrayHasKey('name', $region);
            $this->assertArrayHasKey('display_name', $region);
            $this->assertArrayHasKey('language_family', $region);
            $this->assertArrayHasKey('iso_639_3', $region);
            $this->assertArrayHasKey('regions', $region);
            $this->assertArrayHasKey('boundary_geojson', $region);
        }
    }
}
