<?php

declare(strict_types=1);

namespace Minoo\Seed;

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
}
