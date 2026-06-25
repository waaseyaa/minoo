<?php

declare(strict_types=1);

/**
 * Lesson 1: Kitchen - metadata only (#912).
 *
 * Lessons are now dynamic: the cards are the published, public, curated corpus
 * rows assigned to this lesson's slug (lesson_slug = 'the-kitchen'), grouped by
 * lesson_group and ordered by lesson_weight, rendered by {@see App\Lesson\LessonAssembler}.
 * The Anishinaabemowin is read verbatim from the corpus at runtime; the English
 * is the curated dictionary_entry gloss. This file holds no item list, no
 * Anishinaabemowin, and no media.
 *
 * The 16 original kitchen cards were migrated into the dynamic model (the
 * human-reviewed glosses from research/elder-and-teacher-input/steven-lesson-1-review.md
 * became their dictionary_entry definitions) so the page renders unchanged.
 */
return [
    'title' => 'Lesson 1: Kitchen',
    'slug' => 'the-kitchen',
];
