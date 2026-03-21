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

    /** @return list<array{type: string, name: string, description: string}> */
    public static function oralHistoryTypes(): array
    {
        return [
            ['type' => 'creation_story', 'name' => 'Creation Story', 'description' => 'Origin and creation narratives.'],
            ['type' => 'historical_account', 'name' => 'Historical Account', 'description' => 'Historical events and experiences.'],
            ['type' => 'personal_narrative', 'name' => 'Personal Narrative', 'description' => 'Individual life stories and memories.'],
            ['type' => 'land_teaching', 'name' => 'Land Teaching', 'description' => 'Teachings connected to specific places and the land.'],
            ['type' => 'family_story', 'name' => 'Family Story', 'description' => 'Stories passed down within families.'],
        ];
    }

    /** @return list<array{code: string, name: string, display_name: string, language_family: string, iso_639_3: string, regions: list<string>, boundary_geojson: ?string}> */
    public static function dialectRegions(): array
    {
        return [
            [
                'code' => 'oji-east',
                'name' => 'Nishnaabemwin',
                'display_name' => 'Eastern Ojibwe',
                'language_family' => 'algonquian',
                'iso_639_3' => 'ojg',
                'regions' => ['canada:ontario:north-shore-huron', 'canada:ontario:southern'],
                'boundary_geojson' => null,
            ],
            [
                'code' => 'oji-northwest',
                'name' => 'Anishinaabemowin',
                'display_name' => 'Northwestern Ojibwe',
                'language_family' => 'algonquian',
                'iso_639_3' => 'ojb',
                'regions' => ['canada:ontario:northern'],
                'boundary_geojson' => null,
            ],
            [
                'code' => 'oji-plains',
                'name' => 'Nakawēmowin',
                'display_name' => 'Saulteaux / Plains Ojibwe',
                'language_family' => 'algonquian',
                'iso_639_3' => 'ojs',
                'regions' => ['canada:manitoba:southern', 'canada:saskatchewan'],
                'boundary_geojson' => null,
            ],
            [
                'code' => 'oji-ottawa',
                'name' => 'Odaawaa',
                'display_name' => 'Ottawa / Odawa',
                'language_family' => 'algonquian',
                'iso_639_3' => 'otw',
                'regions' => ['canada:ontario:southern'],
                'boundary_geojson' => null,
            ],
            [
                'code' => 'cree-plains',
                'name' => 'nēhiyawēwin',
                'display_name' => 'Plains Cree',
                'language_family' => 'algonquian',
                'iso_639_3' => 'crk',
                'regions' => ['canada:saskatchewan', 'canada:alberta'],
                'boundary_geojson' => null,
            ],
            [
                'code' => 'cree-swampy',
                'name' => 'Ininīmowin',
                'display_name' => 'Swampy Cree',
                'language_family' => 'algonquian',
                'iso_639_3' => 'csw',
                'regions' => ['canada:manitoba', 'canada:ontario:northern'],
                'boundary_geojson' => null,
            ],
            [
                'code' => 'innu',
                'name' => 'Innu-aimun',
                'display_name' => 'Innu',
                'language_family' => 'algonquian',
                'iso_639_3' => 'moe',
                'regions' => ['canada:quebec', 'canada:atlantic'],
                'boundary_geojson' => null,
            ],
            [
                'code' => 'inuktitut',
                'name' => 'ᐃᓄᒃᑎᑐᑦ',
                'display_name' => 'Inuktitut',
                'language_family' => 'eskimo-aleut',
                'iso_639_3' => 'iku',
                'regions' => ['canada:north:nunavut', 'canada:quebec'],
                'boundary_geojson' => null,
            ],
            [
                'code' => 'inuvialuktun',
                'name' => 'Inuvialuktun',
                'display_name' => 'Inuvialuktun',
                'language_family' => 'eskimo-aleut',
                'iso_639_3' => 'ikt',
                'regions' => ['canada:north:nwt'],
                'boundary_geojson' => null,
            ],
            [
                'code' => 'mohawk',
                'name' => "Kanien\u{2019}kéha",
                'display_name' => 'Mohawk',
                'language_family' => 'iroquoian',
                'iso_639_3' => 'moh',
                'regions' => ['canada:ontario:southern', 'canada:quebec'],
                'boundary_geojson' => null,
            ],
        ];
    }
}
