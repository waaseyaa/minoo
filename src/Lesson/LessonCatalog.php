<?php

declare(strict_types=1);

namespace App\Lesson;

/**
 * The single source of truth for the lesson registry (#878).
 *
 * Lessons are static curation config (config/lessonN.php) keyed by a stable
 * slug — there is no lesson entity. Both the public {@see \App\Http\Controller\Lesson\LessonController}
 * and the Anokii "add to a lesson" curation action read the catalogue here so
 * the slug list never drifts between them.
 */
final class LessonCatalog
{
    /**
     * The lesson registry. Add lessons here.
     *
     * @return list<array{number: int, slug: string, config: string}>
     */
    public static function all(): array
    {
        return [
            ['number' => 1, 'slug' => 'the-kitchen', 'config' => 'lesson1.php'],
        ];
    }

    /**
     * Slug + human title for each lesson, for the Curate dropdown.
     *
     * @return list<array{slug: string, title: string}>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::all() as $lesson) {
            /** @var array{title?: string} $def */
            $def = require self::configDir() . '/' . $lesson['config'];
            $options[] = ['slug' => $lesson['slug'], 'title' => (string) ($def['title'] ?? $lesson['slug'])];
        }

        return $options;
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_map(static fn (array $l): string => $l['slug'], self::all());
    }

    private static function configDir(): string
    {
        return dirname(__DIR__, 2) . '/config';
    }
}
