#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * One-off prod data migration (#912): move the 16 hardcoded Lesson 1 (Kitchen)
 * items into the dynamic lesson model so /lessons/the-kitchen renders unchanged.
 *
 * For each kitchen corpus row it: promotes it to a dictionary_entry (verbatim
 * Ojibwe), overrides the definition with the human-reviewed config gloss
 * (so "stoue"->"stove", "pot or pail"->"pot", case fixes), publishes both
 * entities (status + consent_public = 1, pipeline_status = published), and
 * assigns lesson_slug=the-kitchen with the section label + order.
 *
 * Idempotent: re-running re-asserts the same state. Boots HttpKernel via
 * reflection (prod ConsoleKernel is broken #493).
 *
 * Run: php scripts/migrate_kitchen_to_dynamic.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Ingestion\Curation\UtterancePromoter;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

// [corpus id, curated English gloss, section label, order] - from config/lesson1.php.
$kitchen = [
    ['sb-005', 'bowl', 'Dishes and containers', 0],
    ['sb-017', 'soup bowl', 'Dishes and containers', 1],
    ['sb-020', 'plate', 'Dishes and containers', 2],
    ['sb-006', 'cup, glass', 'Dishes and containers', 3],
    ['sb-028', 'spoon', 'Utensils', 4],
    ['sb-027', 'knife', 'Utensils', 5],
    ['sb-001', 'butter knife', 'Utensils', 6],
    ['sb-016', 'fork', 'Utensils', 7],
    ['sb-019', 'dipper', 'Utensils', 8],
    ['sb-003', 'pot', 'Cookware', 9],
    ['sb-031', 'cooking pot', 'Cookware', 10],
    ['sb-033', 'frying pan', 'Cookware', 11],
    ['sb-022', 'table', 'Furniture and appliances', 12],
    ['sb-009', 'cupboard', 'Furniture and appliances', 13],
    ['sb-011', 'stove', 'Furniture and appliances', 14],
    ['sb-015', 'refrigerator', 'Furniture and appliances', 15],
];

$kernel = new HttpKernel('/app');
(new ReflectionMethod(AbstractKernel::class, 'boot'))->invoke($kernel);
$etm = $kernel->getEntityTypeManager();
$es = $etm->getStorage('example_sentence');
$de = $etm->getStorage('dictionary_entry');
$promoter = new UtterancePromoter($etm);

// Index existing corpus rows by id.
$byId = [];
foreach ($es->loadMultiple($es->getQuery()->accessCheck(false)->condition('source_sentence_id', 'corpus:sb-%', 'LIKE')->execute()) as $row) {
    $byId[str_replace('corpus:', '', (string) $row->get('source_sentence_id'))] = $row;
}

$now = time();
$done = 0;
$missing = [];
foreach ($kitchen as [$id, $gloss, $group, $weight]) {
    $row = $byId[$id] ?? null;
    if ($row === null) {
        $missing[] = $id;
        continue;
    }
    $esid = (int) $row->id();

    // Promote (idempotent) -> dictionary_entry with verbatim Ojibwe word.
    $promotion = $promoter->promote($esid);
    if ($promotion === null) {
        $missing[] = "$id (no ojibwe?)";
        continue;
    }
    $deid = $promotion->dictionaryEntryId;

    // Curated gloss as the JSON-wrapped definition; publish the entry.
    $entry = $de->load($deid);
    $entry->set('definition', (string) json_encode([$gloss], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $entry->set('status', 1);
    $entry->set('consent_public', 1);
    $entry->set('updated_at', $now);
    $de->save($entry);

    // Publish + assign the source row to the lesson.
    $row = $es->load($esid);
    $row->set('status', 1);
    $row->set('consent_public', 1);
    $row->set('pipeline_status', 'published');
    $row->set('lesson_slug', 'the-kitchen');
    $row->set('lesson_group', $group);
    $row->set('lesson_weight', $weight);
    $row->set('updated_at', $now);
    $es->save($row);
    ++$done;
}

echo "kitchen migrated: $done / " . count($kitchen) . "\n";
if ($missing !== []) {
    echo 'MISSING: ' . implode(', ', $missing) . "\n";
}
