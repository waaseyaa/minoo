#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Curate entity-driven content for Elder Newsletter Issue #1.
 *
 * Idempotent: finds or creates the vol=1/issue=1 edition, runs the
 * assembler to select top candidates per section, then enriches
 * editor_blurb on each item from source entity fields.
 *
 * Re-running resets the edition to draft, clears items, and repopulates.
 *
 * Run: php scripts/curate-edition-1.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\Newsletter\Service\EditionLifecycle;
use App\Domain\Newsletter\Service\NewsletterAssembler;
use App\Domain\Newsletter\ValueObject\EditionStatus;
use App\Domain\Newsletter\ValueObject\SectionQuota;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$kernel = new HttpKernel(dirname(__DIR__));
(new ReflectionMethod(AbstractKernel::class, 'boot'))->invoke($kernel);

$etm = $kernel->getEntityTypeManager();
$config = require dirname(__DIR__) . '/config/newsletter.php';

$lifecycle = new EditionLifecycle();
$quotas = SectionQuota::fromConfig($config['sections']);
$assembler = new NewsletterAssembler(
    entityTypeManager: $etm,
    lifecycle: $lifecycle,
    quotas: $quotas,
);

$editionStorage = $etm->getStorage('newsletter_edition');
$itemStorage = $etm->getStorage('newsletter_item');

// --- Find or create Issue #1 ---

$edition = null;
foreach ($editionStorage->loadMultiple() as $e) {
    if ((int) $e->get('volume') === 1 && (int) $e->get('issue_number') === 1) {
        $edition = $e;
        break;
    }
}

if ($edition !== null) {
    echo "Found existing edition neid={$edition->id()} (status: {$edition->get('status')})\n";

    // Reset to draft for idempotent re-assembly
    $status = EditionStatus::fromEntity($edition);
    if ($status === EditionStatus::Curating) {
        $lifecycle->transition($edition, EditionStatus::Draft);
        $editionStorage->save($edition);
        echo "Reset to draft for re-assembly.\n";
    } elseif ($status !== EditionStatus::Draft) {
        fwrite(STDERR, "Edition is in '{$status->value}' state — cannot re-curate. Reset manually.\n");
        exit(1);
    }
} else {
    $publishDate = '2026-05-01';
    $edition = $editionStorage->create([
        'community_id' => null,
        'volume' => 1,
        'issue_number' => 1,
        'publish_date' => $publishDate,
        'status' => 'draft',
        'created_by' => 0,
        'headline' => 'Manitoulin Regional · Issue 1',
    ]);
    $editionStorage->save($edition);
    echo "Created edition neid={$edition->id()}, publish {$publishDate}\n";
}

// --- Run assembler (clears existing items, selects top-N per section) ---

echo "\nRunning assembler...\n";
$assembler->assemble($edition);
$editionStorage->save($edition);

// --- Load assembled items ---

$items = array_filter(
    $itemStorage->loadMultiple(),
    static fn ($i) => (int) $i->get('edition_id') === (int) $edition->id(),
);

if ($items === []) {
    echo "WARNING: assembler produced 0 items. Edition stays in draft.\n";
    echo "Check that you have content (events, teachings, dictionary entries, posts) in the local DB.\n";
    exit(1);
}

// --- Enrich editor blurbs from source entities ---

echo "Enriching editor blurbs from source entities...\n\n";

foreach ($items as $item) {
    $sourceType = (string) $item->get('source_type');
    $sourceId = (int) $item->get('source_id');

    $storage = $etm->getStorage($sourceType);
    $source = $storage->load($sourceId);
    if ($source === null) {
        continue;
    }

    $blurb = buildBlurb($sourceType, $source);
    $item->set('editor_blurb', $blurb);
    $itemStorage->save($item);
}

// --- Report results ---

$quotaMap = [];
foreach ($quotas as $q) {
    $quotaMap[$q->name] = $q->quota;
}

$bySection = [];
foreach ($items as $item) {
    $bySection[(string) $item->get('section')][] = $item;
}

echo "=== Issue #1 Curation Report ===\n\n";

$shortfalls = [];
$sectionOrder = ['news', 'events', 'teachings', 'language', 'community'];

foreach ($sectionOrder as $section) {
    $sectionItems = $bySection[$section] ?? [];
    $quota = $quotaMap[$section] ?? 0;
    $filled = count($sectionItems);
    $status = $filled >= $quota ? 'FILLED' : 'SHORT';

    echo sprintf("%-12s %d/%d  [%s]\n", $section, $filled, $quota, $status);

    foreach ($sectionItems as $item) {
        $blurb = (string) $item->get('editor_blurb');
        $blurb = mb_strlen($blurb) > 80 ? mb_substr($blurb, 0, 77) . '...' : $blurb;
        echo "  · {$blurb}\n";
    }

    if ($filled < $quota) {
        $shortfalls[$section] = $quota - $filled;
    }

    echo "\n";
}

echo "Total items: " . count($items) . "\n";
echo "Edition status: " . $edition->get('status') . "\n";

if ($shortfalls !== []) {
    echo "\nSHORTFALLS:\n";
    foreach ($shortfalls as $section => $deficit) {
        echo "  {$section}: {$deficit} item(s) below quota\n";
    }
    echo "\nConsider adjusting quotas in docs/plans/newsletter-issue-1-content-plan.md\n";
}

echo "\n--- DONE ---\n";
echo "Edition ID: " . $edition->id() . "\n";
echo "Re-run this script to re-curate. Items are cleared and repopulated each time.\n";

// --- Blurb builders ---

function buildBlurb(string $sourceType, EntityInterface $entity): string
{
    return match ($sourceType) {
        'event' => buildEventBlurb($entity),
        'teaching' => buildTeachingBlurb($entity),
        'post' => buildPostBlurb($entity),
        'dictionary_entry' => buildDictionaryBlurb($entity),
        default => (string) ($entity->get('title') ?? $entity->label()),
    };
}

function buildEventBlurb(EntityInterface $event): string
{
    $title = (string) ($event->get('title') ?? '');
    $location = (string) ($event->get('location') ?? '');
    $startsAt = (string) ($event->get('starts_at') ?? '');

    $parts = [$title];

    if ($startsAt !== '') {
        $ts = strtotime($startsAt);
        if ($ts !== false) {
            $parts[] = date('F j', $ts);
        }
    }

    if ($location !== '') {
        $parts[] = $location;
    }

    return implode(' — ', $parts);
}

function buildTeachingBlurb(EntityInterface $teaching): string
{
    $title = (string) ($teaching->get('title') ?? '');
    $content = (string) ($teaching->get('content') ?? '');

    if ($content === '') {
        return $title;
    }

    // Strip HTML, take first sentence
    $plain = strip_tags($content);
    $plain = trim($plain);

    $firstSentence = preg_match('/^(.+?[.!?])\s/', $plain, $m) ? $m[1] : '';

    if ($firstSentence !== '' && mb_strlen($firstSentence) <= 120) {
        return $title . ' — ' . $firstSentence;
    }

    // Truncate to ~100 chars
    $summary = mb_substr($plain, 0, 100);
    $lastSpace = mb_strrpos($summary, ' ');
    if ($lastSpace !== false && $lastSpace > 60) {
        $summary = mb_substr($summary, 0, $lastSpace);
    }

    return $title . ' — ' . $summary . '...';
}

function buildPostBlurb(EntityInterface $post): string
{
    $body = (string) ($post->get('body') ?? '');
    $plain = trim(strip_tags($body));

    if ($plain === '') {
        return '(Community post)';
    }

    // Take first sentence or truncate
    $firstSentence = preg_match('/^(.+?[.!?])\s/', $plain, $m) ? $m[1] : '';

    if ($firstSentence !== '' && mb_strlen($firstSentence) <= 120) {
        return $firstSentence;
    }

    $summary = mb_substr($plain, 0, 100);
    $lastSpace = mb_strrpos($summary, ' ');
    if ($lastSpace !== false && $lastSpace > 60) {
        $summary = mb_substr($summary, 0, $lastSpace);
    }

    return $summary . '...';
}

function buildDictionaryBlurb(EntityInterface $entry): string
{
    $word = (string) ($entry->get('word') ?? '');
    $definition = (string) ($entry->get('definition') ?? '');

    // Definition may be JSON-wrapped (e.g. ["bear"])
    $decoded = json_decode($definition, true);
    if (is_array($decoded)) {
        $definition = implode('; ', $decoded);
    }

    $pos = (string) ($entry->get('part_of_speech') ?? '');
    $posLabel = $pos !== '' ? " ({$pos})" : '';

    if ($definition !== '') {
        return $word . $posLabel . ' — ' . $definition;
    }

    return $word . $posLabel;
}
