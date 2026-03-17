#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Create featured items for LNHL 2026 content.
 * Run: php scripts/populate_featured.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$kernel = new HttpKernel(dirname(__DIR__));
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);

$entityTypeManager = $kernel->getEntityTypeManager();
$featuredStorage = $entityTypeManager->getStorage('featured_item');

// 1. Find LNHL event
$eventStorage = $entityTypeManager->getStorage('event');
$eventIds = $eventStorage->getQuery()->condition('slug', 'little-nhl-2026')->execute();

if ($eventIds !== []) {
    $eventId = reset($eventIds);
    echo "Found LNHL event (eid: {$eventId})\n";

    $existing = $featuredStorage->getQuery()
        ->condition('entity_type', 'event')
        ->condition('entity_id', $eventId)
        ->execute();

    if ($existing === []) {
        $featured = new \Minoo\Entity\FeaturedItem([
            'entity_type' => 'event',
            'entity_id' => (int) $eventId,
            'headline' => 'Little NHL 2026',
            'subheadline' => '271 teams, 4,500+ players — Markham, Ontario · March 15–19',
            'weight' => 100,
            'starts_at' => '2026-03-10 00:00:00',
            'ends_at' => '2026-03-21 23:59:59',
            'status' => 1,
        ]);
        $featuredStorage->save($featured);
        echo "Created featured item for LNHL (fid: {$featured->id()})\n";
    } else {
        echo "LNHL already featured, skipping.\n";
    }
} else {
    echo "Warning: LNHL event not found. Run populate_lnhl.php first.\n";
}

// 2. Find Crystal Shawanda
$personStorage = $entityTypeManager->getStorage('resource_person');
$personIds = $personStorage->getQuery()->condition('slug', 'crystal-shawanda')->execute();

if ($personIds !== []) {
    $personId = reset($personIds);
    echo "\nFound Crystal Shawanda (rpid: {$personId})\n";

    $existing = $featuredStorage->getQuery()
        ->condition('entity_type', 'resource_person')
        ->condition('entity_id', $personId)
        ->execute();

    if ($existing === []) {
        $featured = new \Minoo\Entity\FeaturedItem([
            'entity_type' => 'resource_person',
            'entity_id' => (int) $personId,
            'headline' => 'Crystal Shawanda at Little NHL',
            'subheadline' => 'Ojibwe country/blues artist drove from Nashville for the tournament',
            'weight' => 50,
            'starts_at' => '2026-03-15 00:00:00',
            'ends_at' => '2026-03-31 23:59:59',
            'status' => 1,
        ]);
        $featuredStorage->save($featured);
        echo "Created featured item for Crystal Shawanda (fid: {$featured->id()})\n";
    } else {
        echo "Crystal Shawanda already featured, skipping.\n";
    }
} else {
    echo "Warning: Crystal Shawanda not found. Run populate_lnhl.php first.\n";
}

echo "\nDone. Visit homepage to verify featured section.\n";
