<?php

declare(strict_types=1);

namespace App\Seed;

final class ContentSeeder
{
    /** @return list<array{title: string, slug: string, type: string, description: string, location: string, starts_at: string, ends_at?: string, status: int}> */
    public static function events(): array
    {
        return [
            [
                'title' => 'Summer Solstice Powwow',
                'slug' => 'summer-solstice-powwow',
                'type' => 'powwow',
                'description' => "Join us on Manitoulin Island for the annual Summer Solstice Powwow, a two-day celebration of Indigenous culture, music, and community. The gathering features grand entry ceremonies, traditional and contemporary dance competitions, drum circles, artisan vendors, and a community feast.\n\nAll nations and visitors are welcome. Bring your own chair and tobacco for offerings. Camping is available on-site.",
                'location' => 'Manitoulin Island, Ontario',
                'starts_at' => '2026-06-21',
                'ends_at' => '2026-06-22',
                'status' => 1,
            ],
            [
                'title' => 'Community Healing Circle',
                'slug' => 'community-healing-circle',
                'type' => 'gathering',
                'description' => "The Community Healing Circle meets weekly to provide a safe, supportive environment for individuals and families seeking healing through traditional practices. Each session is guided by an Elder and includes smudging, sharing circles, and Teachings.\n\nNo registration required. Light refreshments provided. Childcare available on request.",
                'location' => 'Sudbury Community Centre',
                'starts_at' => '2026-04-01',
                'status' => 1,
            ],
            [
                'title' => 'Spring Water Ceremony',
                'slug' => 'water-ceremony',
                'type' => 'ceremony',
                'description' => "On World Water Day, we gather at the shores of Lake Huron to honour nibi (water) through prayer, song, and offerings. The ceremony is led by Water Walkers and community Elders who carry the tradition of caring for the water.\n\nParticipants are asked to bring a small container of water from their home to add to the collective offering. Dress warmly and wear footwear suitable for the shoreline.",
                'location' => 'Lake Huron shoreline, Sauble Beach',
                'starts_at' => '2026-03-22',
                'status' => 1,
            ],
        ];
    }

    /** @return list<array{title: string, slug: string, type: string, content: string, status: int}> */
    public static function teachings(): array
    {
        return [
            [
                'title' => 'The Seven Grandfather Teachings',
                'slug' => 'seven-grandfather-teachings',
                'type' => 'culture',
                'content' => "The Seven Grandfather Teachings — Nibwaakaawin (Wisdom), Zaagi'idiwin (Love), Minaadendamowin (Respect), Aakode'ewin (Bravery), Gwayakwaadiziwin (Honesty), Dabaadendiziwin (Humility), and Debwewin (Truth) — are foundational values in Anishinaabe life.\n\nThese teachings guide how we relate to one another, to the land, and to all living beings. They are not rules imposed from outside but truths carried forward by Elders and Knowledge Keepers across generations.",
                'status' => 1,
            ],
            [
                'title' => 'The Medicine Wheel',
                'slug' => 'medicine-wheel',
                'type' => 'culture',
                'content' => "The Medicine Wheel represents the interconnectedness of all aspects of life — physical, emotional, mental, and spiritual. Each direction carries its own teachings, colours, and medicines.\n\nThe wheel teaches us that balance is essential to well-being: when one aspect of our lives is neglected, the whole circle is affected. It is used in healing, ceremony, and daily reflection.",
                'status' => 1,
            ],
            [
                'title' => 'Treaty Relationships',
                'slug' => 'treaty-relationships',
                'type' => 'history',
                'content' => "Treaties between Indigenous nations and the Crown are living agreements — not historical artifacts. They established a relationship of mutual respect, shared land stewardship, and coexistence.\n\nUnderstanding treaty history is essential for all people living on this land. Treaties are not just Indigenous history — they are Canadian history, and they carry obligations that remain in effect today.",
                'status' => 1,
            ],
        ];
    }

    /** @return list<array{name: string, slug: string, type: string, description: string, region: string, status: int}> */
    public static function groups(): array
    {
        return [
            [
                'name' => 'Indigenous Youth Network',
                'slug' => 'indigenous-youth-network',
                'type' => 'online',
                'description' => "A digital community connecting Indigenous youth across Turtle Island for cultural exchange, mentorship, and mutual support. Weekly virtual gatherings feature guest speakers, language lessons, and creative workshops.\n\nWhether you're in a city or on reserve, this network is your space to connect, learn, and grow with other young Indigenous people across Turtle Island.",
                'region' => 'National (online)',
                'status' => 1,
            ],
            [
                'name' => "N'Swakamok Indigenous Friendship Centre",
                'slug' => 'nswakamok-indigenous-friendship-centre',
                'type' => 'offline',
                'description' => "The N'Swakamok Indigenous Friendship Centre has served the Indigenous community in the Sudbury region for over four decades. We offer a wide range of programming including cultural workshops, family support services, Elder counselling, youth mentorship, and community events.\n\nOur centre is open to all Indigenous peoples and their families, regardless of status or nation. Drop in during regular hours or check our events calendar for upcoming programs.",
                'region' => 'Northern Ontario',
                'status' => 1,
            ],
            [
                'name' => 'Great Lakes Water Protectors',
                'slug' => 'great-lakes-water-protectors',
                'type' => 'advocacy',
                'description' => "A grassroots alliance of Indigenous and non-Indigenous water protectors dedicated to safeguarding the Great Lakes watershed through legal advocacy, community-based water quality monitoring, public education campaigns, and direct action.\n\nWe believe that water is sacred and that protecting nibi is both a treaty right and a shared responsibility. Join us in this vital work — whether through volunteering, donating, or simply learning more about the issues facing our waters.",
                'region' => 'Great Lakes Region',
                'status' => 1,
            ],
        ];
    }

    /** @return list<array{word: string, slug: string, definition: string, part_of_speech: string, language_code: string, status: int}> */
    public static function dictionaryEntries(): array
    {
        return [
            [
                'word' => 'nibi',
                'slug' => 'nibi',
                'definition' => 'Water. One of the most sacred elements in Anishinaabe cosmology, nibi sustains all life.',
                'part_of_speech' => 'ni',
                'language_code' => 'oj',
                'status' => 1,
            ],
            [
                'word' => 'makwa',
                'slug' => 'makwa',
                'definition' => 'Bear. The bear is a powerful symbol of strength, healing, and the Medicine Lodge.',
                'part_of_speech' => 'na',
                'language_code' => 'oj',
                'status' => 1,
            ],
            [
                'word' => 'miigwech',
                'slug' => 'miigwech',
                'definition' => 'Thank you. A word of gratitude used daily, in ceremony, and in prayer.',
                'part_of_speech' => 'vai',
                'language_code' => 'oj',
                'status' => 1,
            ],
        ];
    }
}
