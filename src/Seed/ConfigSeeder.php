<?php

declare(strict_types=1);

namespace Minoo\Seed;

final class ConfigSeeder
{
    /** @return list<array{type: string, name: string, description: string}> */
    public static function eventTypes(): array
    {
        return [
            ['type' => 'powwow', 'name' => 'Powwow', 'description' => 'Traditional gathering with dance and song.'],
            ['type' => 'gathering', 'name' => 'Gathering', 'description' => 'Community gathering or meeting.'],
            ['type' => 'ceremony', 'name' => 'Ceremony', 'description' => 'Sacred or cultural ceremony.'],
            ['type' => 'tournament', 'name' => 'Tournament', 'description' => 'Sports tournament or competitive event.'],
        ];
    }

    /** @return list<array{type: string, name: string}> */
    public static function groupTypes(): array
    {
        return [
            ['type' => 'online', 'name' => 'Online Community'],
            ['type' => 'offline', 'name' => 'Local Community'],
            ['type' => 'advocacy', 'name' => 'Advocacy Organization'],
            ['type' => 'business', 'name' => 'Local Business'],
        ];
    }

    /** @return list<array{type: string, name: string}> */
    public static function teachingTypes(): array
    {
        return [
            ['type' => 'culture', 'name' => 'Culture'],
            ['type' => 'history', 'name' => 'History'],
            ['type' => 'language', 'name' => 'Language'],
        ];
    }
}
