<?php

declare(strict_types=1);

namespace App\Seed;

final class VolunteerSkillSeeder
{
    /** @return array{vocabulary: array{vid: string, name: string}, terms: list<array{name: string}>} */
    public static function volunteerSkillsVocabulary(): array
    {
        return [
            'vocabulary' => [
                'vid' => 'volunteer_skills',
                'name' => 'Volunteer Skills',
            ],
            'terms' => [
                ['name' => 'Rides'],
                ['name' => 'Groceries'],
                ['name' => 'Chores'],
                ['name' => 'Visits / Companionship'],
            ],
        ];
    }
}
