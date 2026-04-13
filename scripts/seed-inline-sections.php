#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Seed inline-only newsletter_item rows for an edition.
 *
 * Inline sections (cover, editor's note, jokes, puzzles, horoscope,
 * elder spotlight, back page) are hand-authored content that the
 * NewsletterAssembler does not touch. This script creates placeholder
 * items so the template can render them in the correct position.
 *
 * Idempotent: clears existing inline items for the edition and
 * re-inserts them. Entity-driven items are left untouched.
 *
 * Usage: php scripts/seed-inline-sections.php [edition_id]
 *   edition_id defaults to 1 (Issue #1).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$editionId = (int) ($argv[1] ?? 1);

$kernel = new HttpKernel(dirname(__DIR__));
(new ReflectionMethod(AbstractKernel::class, 'boot'))->invoke($kernel);

$etm = $kernel->getEntityTypeManager();
$config = require dirname(__DIR__) . '/config/newsletter.php';

$editionStorage = $etm->getStorage('newsletter_edition');
$edition = $editionStorage->load($editionId);
if ($edition === null) {
    fwrite(STDERR, "Edition {$editionId} not found. Run scripts/curate-edition-1.php first.\n");
    exit(1);
}

echo "Seeding inline sections for edition neid={$editionId} ({$edition->label()})\n\n";

$itemStorage = $etm->getStorage('newsletter_item');
$inlineSections = $config['inline_sections'] ?? [];
$inlineSlugs = array_keys($inlineSections);

// Clear existing inline items for this edition (source_type = 'inline').
$existing = array_filter(
    $itemStorage->loadMultiple(),
    static fn($i) => (int) $i->get('edition_id') === $editionId
        && (string) $i->get('source_type') === 'inline',
);
if ($existing !== []) {
    $itemStorage->delete(array_values($existing));
    echo "Cleared " . count($existing) . " existing inline item(s).\n";
}

// Determine starting position after entity-driven items.
$entityItems = array_filter(
    $itemStorage->loadMultiple(),
    static fn($i) => (int) $i->get('edition_id') === $editionId
        && (string) $i->get('source_type') !== 'inline',
);
$maxPosition = 0;
foreach ($entityItems as $item) {
    $maxPosition = max($maxPosition, (int) $item->get('position'));
}

// Placeholder content keyed by section slug.
$placeholders = [
    'cover' => [
        'title' => 'Minoo Elder Newsletter — Issue #1',
        'body' => '[Cover layout: masthead, issue date, regional tagline. Content to be finalized by editor.]',
    ],
    'editors_note' => [
        'title' => "Editor's Note",
        'body' => "[Welcome message and brief preview of what's inside. 150--200 words. To be written by editor.]",
    ],
    'language_corner' => [
        'title' => 'Anishinaabemowin Corner',
        'body' => '[Vocabulary, phrases, and cultural context for this issue. Pronunciation guidance where possible. Connect to the season or a theme from this issue. Awaiting collaborator input from Barbara Nolan. To be hand-authored per issue.]',
    ],
    'jokes' => [
        'title' => 'Jokes & Humour',
        'body' => '[2--3 short, clean jokes appropriate for Elders. Community-oriented humour. To be written by editor.]',
    ],
    'puzzles' => [
        'title' => 'Puzzles',
        'body' => '[Word search, crossword, or trivia quiz themed to the season. Include answer key. To be created by editor.]',
    ],
    'horoscope' => [
        'title' => 'Anishinaabe Horoscope',
        'body' => '[Seasonal horoscope by clan animal (Bear, Loon, Crane, etc.). 1--2 sentences per clan. Light, positive, grounded in seasonal rhythms. To be written by editor.]',
    ],
    'elder_spotlight' => [
        'title' => 'Elder Spotlight',
        'body' => '[Elder profile or interview. ~150 words. Name, community, and what they want readers to know. Photo placeholder. To be written by editor.]',
    ],
    'back_page' => [
        'title' => 'Back Page',
        'body' => '[Community coordinator contacts, next issue date, miigwech closing, volunteer call-to-action. To be finalized by editor.]',
    ],
];

$position = $maxPosition;
$seeded = 0;

foreach ($inlineSections as $slug => $sectionDef) {
    $placeholder = $placeholders[$slug] ?? [
        'title' => $sectionDef['label'],
        'body' => '[Placeholder — to be written by editor.]',
    ];

    $item = $itemStorage->create([
        'edition_id' => $editionId,
        'position' => ++$position,
        'section' => $slug,
        'source_type' => 'inline',
        'source_id' => 0,
        'inline_title' => $placeholder['title'],
        'inline_body' => $placeholder['body'],
        'editor_blurb' => $placeholder['title'],
        'included' => 1,
    ]);
    $itemStorage->save($item);
    $seeded++;

    echo sprintf("  [%2d] %-18s  %s\n", $position, $slug, $placeholder['title']);
}

echo "\nSeeded {$seeded} inline section(s).\n";
echo "Edition has " . count($entityItems) . " entity-driven + {$seeded} inline = "
    . (count($entityItems) + $seeded) . " total items.\n";
echo "\nReplace placeholder content by editing inline_title/inline_body on these items.\n";
