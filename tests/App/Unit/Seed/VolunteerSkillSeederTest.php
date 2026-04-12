<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seed;

use App\Seed\VolunteerSkillSeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VolunteerSkillSeeder::class)]
final class VolunteerSkillSeederTest extends TestCase
{
    #[Test]
    public function it_provides_volunteer_skills_vocabulary_with_terms(): void
    {
        $data = VolunteerSkillSeeder::volunteerSkillsVocabulary();

        $this->assertSame('volunteer_skills', $data['vocabulary']['vid']);
        $this->assertSame('Volunteer Skills', $data['vocabulary']['name']);
        $this->assertCount(4, $data['terms']);
        $this->assertSame('Rides', $data['terms'][0]['name']);
        $this->assertSame('Visits / Companionship', $data['terms'][3]['name']);
    }
}
