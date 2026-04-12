<?php

declare(strict_types=1);

namespace App\Seed;

final class TaxonomySeeder
{
    /** @return array{vocabulary: array<string, string>, terms: list<array<string, string>>} */
    public static function galleryVocabulary(): array
    {
        return [
            'vocabulary' => ['vid' => 'gallery', 'name' => 'Gallery', 'description' => 'Cultural collection gallery categories.'],
            'terms' => [
                ['name' => 'fishing', 'vid' => 'gallery'],
                ['name' => 'sugaring', 'vid' => 'gallery'],
                ['name' => 'lodges', 'vid' => 'gallery'],
                ['name' => 'hidework', 'vid' => 'gallery'],
                ['name' => 'ricing', 'vid' => 'gallery'],
                ['name' => 'wintertravel', 'vid' => 'gallery'],
            ],
        ];
    }

    /** @return array{vocabulary: array<string, string>, terms: list<array<string, string>>} */
    public static function teachingTagsVocabulary(): array
    {
        return [
            'vocabulary' => ['vid' => 'teaching_tags', 'name' => 'Teaching Tags', 'description' => 'Cross-cutting topic tags for teachings.'],
            'terms' => [
                ['name' => 'ceremony', 'vid' => 'teaching_tags'],
                ['name' => 'governance', 'vid' => 'teaching_tags'],
                ['name' => 'land', 'vid' => 'teaching_tags'],
                ['name' => 'kinship', 'vid' => 'teaching_tags'],
                ['name' => 'language', 'vid' => 'teaching_tags'],
                ['name' => 'history', 'vid' => 'teaching_tags'],
            ],
        ];
    }

    /** @return array{vocabulary: array<string, string>, terms: list<array<string, string>>} */
    public static function personRolesVocabulary(): array
    {
        return [
            'vocabulary' => ['vid' => 'person_roles', 'name' => 'Person Roles', 'description' => 'Community roles for resource people.'],
            'terms' => [
                ['name' => 'Elder', 'vid' => 'person_roles'],
                ['name' => 'Knowledge Keeper', 'vid' => 'person_roles'],
                ['name' => 'Dancer', 'vid' => 'person_roles'],
                ['name' => 'Drummer', 'vid' => 'person_roles'],
                ['name' => 'Language Speaker', 'vid' => 'person_roles'],
                ['name' => 'Regalia Maker', 'vid' => 'person_roles'],
                ['name' => 'Caterer', 'vid' => 'person_roles'],
                ['name' => 'Crafter', 'vid' => 'person_roles'],
                ['name' => 'Workshop Facilitator', 'vid' => 'person_roles'],
                ['name' => 'Small Business Owner', 'vid' => 'person_roles'],
                ['name' => 'Youth Worker', 'vid' => 'person_roles'],
                ['name' => 'Cedar Harvester', 'vid' => 'person_roles'],
                ['name' => 'Artist', 'vid' => 'person_roles'],
                ['name' => 'Web Developer', 'vid' => 'person_roles'],
                ['name' => 'Healer', 'vid' => 'person_roles'],
            ],
        ];
    }

    /** @return array{vocabulary: array<string, string>, terms: list<array<string, string>>} */
    public static function personOfferingsVocabulary(): array
    {
        return [
            'vocabulary' => ['vid' => 'person_offerings', 'name' => 'Person Offerings', 'description' => 'Services and products offered by resource people.'],
            'terms' => [
                ['name' => 'Food', 'vid' => 'person_offerings'],
                ['name' => 'Regalia', 'vid' => 'person_offerings'],
                ['name' => 'Crafts', 'vid' => 'person_offerings'],
                ['name' => 'Teachings', 'vid' => 'person_offerings'],
                ['name' => 'Workshops', 'vid' => 'person_offerings'],
                ['name' => 'Cultural Services', 'vid' => 'person_offerings'],
                ['name' => 'Performances', 'vid' => 'person_offerings'],
                ['name' => 'Cedar Products', 'vid' => 'person_offerings'],
                ['name' => 'Beadwork', 'vid' => 'person_offerings'],
                ['name' => 'Traditional Medicine', 'vid' => 'person_offerings'],
                ['name' => 'Hair Services', 'vid' => 'person_offerings'],
                ['name' => 'Esthetics', 'vid' => 'person_offerings'],
                ['name' => 'Massage', 'vid' => 'person_offerings'],
                ['name' => 'Nail Services', 'vid' => 'person_offerings'],
                ['name' => 'Web Development', 'vid' => 'person_offerings'],
            ],
        ];
    }
}
