#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Draft a brand new Newsletter Edition end-to-end (no HTTP, no UI).
 *
 * Creates a NewsletterEdition for the configured default community,
 * runs the NewsletterAssembler against current local content, then
 * transitions the edition through draft -> curating -> approved.
 *
 * The PDF render step is intentionally NOT done here — pair this with
 * `bin/newsletter-render-smoke <id>` which boots the dev server and
 * drives bin/render-pdf.js (Playwright) end-to-end. Splitting them keeps
 * this script Chromium-free and re-runnable in any environment.
 *
 * Run: php scripts/draft-newsletter-edition.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Minoo\Domain\Newsletter\Service\EditionLifecycle;
use Minoo\Domain\Newsletter\Service\NewsletterAssembler;
use Minoo\Domain\Newsletter\ValueObject\SectionQuota;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$kernel = new HttpKernel(dirname(__DIR__));
(new ReflectionMethod(AbstractKernel::class, 'boot'))->invoke($kernel);

$etm = $kernel->getEntityTypeManager();
$config = require dirname(__DIR__) . '/config/newsletter.php';

$lifecycle = new EditionLifecycle();
$assembler = new NewsletterAssembler(
    entityTypeManager: $etm,
    lifecycle: $lifecycle,
    quotas: SectionQuota::fromConfig($config['sections']),
);

$editionStorage = $etm->getStorage('newsletter_edition');

$publishDate = (new DateTimeImmutable('first day of next month'))->format('Y-m-d');
$community = null; // null = regional issue (matches default_community config)

// Auto-issue number
$existing = array_filter(
    $editionStorage->loadMultiple(),
    static fn ($e) => (string) $e->get('community_id') === (string) $community,
);
$nextIssue = 1;
foreach ($existing as $e) {
    $nextIssue = max($nextIssue, ((int) $e->get('issue_number')) + 1);
}

echo "Creating Edition: vol 1, issue {$nextIssue}, publish {$publishDate}, regional\n";

$edition = $editionStorage->create([
    'community_id' => $community,
    'volume' => 1,
    'issue_number' => $nextIssue,
    'publish_date' => $publishDate,
    'status' => 'draft',
    'created_by' => 0,
    'headline' => sprintf('Manitoulin Regional · Issue %d', $nextIssue),
]);
$editionStorage->save($edition);

echo "Saved edition neid={$edition->id()}\n";
echo "Running assembler...\n";

$assembler->assemble($edition);
$editionStorage->save($edition);

$itemStorage = $etm->getStorage('newsletter_item');
$items = array_filter(
    $itemStorage->loadMultiple(),
    static fn ($i) => (int) $i->get('edition_id') === (int) $edition->id(),
);

if ($items === []) {
    echo "FAIL: assembler produced 0 items. Edition is still in draft. Check that you have content (events, dictionary entries) in the local DB.\n";
    exit(1);
}

$bySection = [];
foreach ($items as $item) {
    $bySection[(string) $item->get('section')][] = $item;
}

echo "Assembled " . count($items) . " items across " . count($bySection) . " sections:\n";
foreach ($bySection as $section => $sectionItems) {
    echo sprintf("  - %-10s %d item(s)\n", $section, count($sectionItems));
    foreach ($sectionItems as $item) {
        $blurb = (string) $item->get('editor_blurb');
        $blurb = $blurb !== '' ? substr($blurb, 0, 60) : '(no title)';
        echo "      · {$blurb}\n";
    }
}

echo "Edition status now: " . $edition->get('status') . "\n";

echo "Approving edition...\n";
$lifecycle->approve($edition, 0);
$editionStorage->save($edition);
echo "Edition status now: " . $edition->get('status') . "\n";

echo "\n--- DONE ---\n";
echo "Edition ID: " . $edition->id() . "\n";
echo "Next step: bin/newsletter-render-smoke " . $edition->id() . "\n";
