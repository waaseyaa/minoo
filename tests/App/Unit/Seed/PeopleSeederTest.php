<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seed;

use App\Seed\PeopleSeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PeopleSeeder::class)]
final class PeopleSeederTest extends TestCase
{
    #[Test]
    public function it_provides_sample_people(): void
    {
        $people = PeopleSeeder::samplePeople();

        $this->assertCount(4, $people);
        $this->assertSame('Mary Trudeau', $people[0]['name']);
        $this->assertSame('mary-trudeau', $people[0]['slug']);
        $this->assertArrayHasKey('bio', $people[0]);
        $this->assertArrayHasKey('community', $people[0]);
        $this->assertArrayHasKey('email', $people[0]);
    }
}
