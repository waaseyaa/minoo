<?php

declare(strict_types=1);

/**
 * Lesson 1: Kitchen. Curation metadata ONLY.
 *
 * This file holds the presentation order, section groupings, and the
 * human-reviewed English glosses from
 * research/elder-and-teacher-input/steven-lesson-1-review.md (Steven Bennett's
 * Lesson 1 review). It is safe to commit: it contains no Anishinaabemowin and
 * no audio or whiteboard media.
 *
 * The Anishinaabemowin for each card is read at runtime, verbatim, from the
 * migrated corpus (example_sentence rows keyed by source_sentence_id
 * "corpus:<id>"), so the community-controlled orthography is never committed to
 * this repository and is never altered. Audio and whiteboard thumbnails are
 * streamed from the corpus directory by LessonController::media().
 */
return [
    'title' => 'Lesson 1: Kitchen',
    'groups' => [
        [
            'label' => 'Dishes and containers',
            'items' => [
                ['id' => 'sb-005', 'english' => 'bowl'],
                ['id' => 'sb-017', 'english' => 'soup bowl'],
                ['id' => 'sb-020', 'english' => 'plate'],
                ['id' => 'sb-006', 'english' => 'cup, glass'],
            ],
        ],
        [
            'label' => 'Utensils',
            'items' => [
                ['id' => 'sb-028', 'english' => 'spoon'],
                ['id' => 'sb-027', 'english' => 'knife'],
                ['id' => 'sb-001', 'english' => 'butter knife'],
                ['id' => 'sb-016', 'english' => 'fork'],
                ['id' => 'sb-019', 'english' => 'dipper'],
            ],
        ],
        [
            'label' => 'Cookware',
            'items' => [
                ['id' => 'sb-003', 'english' => 'pot'],
                ['id' => 'sb-031', 'english' => 'cooking pot'],
                ['id' => 'sb-033', 'english' => 'frying pan'],
            ],
        ],
        [
            'label' => 'Furniture and appliances',
            'items' => [
                ['id' => 'sb-022', 'english' => 'table'],
                ['id' => 'sb-009', 'english' => 'cupboard'],
                ['id' => 'sb-011', 'english' => 'stove'],
                ['id' => 'sb-015', 'english' => 'refrigerator'],
            ],
        ],
    ],
];
