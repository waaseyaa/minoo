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

        $this->assertCount(3, $types);
        $this->assertSame('powwow', $types[0]['type']);
        $this->assertSame('Powwow', $types[0]['name']);
    }

    #[Test]
    public function it_provides_group_types(): void
    {
        $types = ConfigSeeder::groupTypes();

        $this->assertCount(3, $types);
        $this->assertSame('online', $types[0]['type']);
    }

    #[Test]
    public function it_provides_teaching_types(): void
    {
        $types = ConfigSeeder::teachingTypes();

        $this->assertCount(3, $types);
        $this->assertSame('culture', $types[0]['type']);
    }
}
