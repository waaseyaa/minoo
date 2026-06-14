<?php

declare(strict_types=1);

namespace App\Domain\Games;

/**
 * Curated crossword theme packs. Each theme groups dictionary entries by the
 * English gloss in their definition (the Anishinaabemowin headwords themselves
 * are read verbatim from the dictionary, never invented or re-categorised by
 * meaning). Keyword matching is a selection aid for building themed grids, not
 * a claim about the words' semantics.
 */
final class CrosswordThemes
{
    /**
     * @var array<string, array{name: string, keywords: list<string>}>
     */
    private const THEMES = [
        'animals' => [
            'name' => 'Animals',
            'keywords' => [
                'bear', 'wolf', 'eagle', 'fish', 'deer', 'moose', 'turtle', 'rabbit',
                'bird', 'fox', 'owl', 'beaver', 'otter', 'duck', 'loon', 'frog',
                'snake', 'dog', 'squirrel', 'lynx', 'hare', 'crane', 'hawk',
            ],
        ],
        'nature' => [
            'name' => 'Land and sky',
            'keywords' => [
                'tree', 'river', 'lake', 'water', 'stone', 'rock', 'sky', 'star',
                'sun', 'moon', 'wind', 'fire', 'earth', 'mountain', 'forest', 'leaf',
                'flower', 'grass', 'snow', 'ice', 'cloud', 'island', 'hill',
            ],
        ],
        'body' => [
            'name' => 'The body',
            'keywords' => [
                'hand', 'head', 'eye', 'foot', 'heart', 'arm', 'leg', 'mouth',
                'nose', 'ear', 'hair', 'tooth', 'finger', 'face', 'body', 'bone',
                'skin', 'blood', 'knee', 'back',
            ],
        ],
        'family' => [
            'name' => 'Family',
            'keywords' => [
                'mother', 'father', 'child', 'sister', 'brother', 'grandmother',
                'grandfather', 'son', 'daughter', 'family', 'friend', 'baby',
                'woman', 'man', 'aunt', 'uncle', 'cousin',
            ],
        ],
    ];

    /**
     * @return array<string, array{name: string, keywords: list<string>}>
     */
    public static function all(): array
    {
        return self::THEMES;
    }

    public static function exists(string $slug): bool
    {
        return isset(self::THEMES[$slug]);
    }

    public static function name(string $slug): string
    {
        return self::THEMES[$slug]['name'] ?? ucfirst($slug);
    }

    /**
     * @return list<string>
     */
    public static function keywords(string $slug): array
    {
        return self::THEMES[$slug]['keywords'] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::THEMES);
    }
}
