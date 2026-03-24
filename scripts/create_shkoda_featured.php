#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Create a featured item announcing the Ishkode word game.
 * Run: php scripts/create_ishkode_featured.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$kernel = new HttpKernel(dirname(__DIR__));
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);

$entityTypeManager = $kernel->getEntityTypeManager();
$featuredStorage = $entityTypeManager->getStorage('featured_item');

// Check if already exists
$existing = $featuredStorage->getQuery()
    ->condition('headline', 'Ishkode — Word Game')
    ->execute();

if ($existing !== []) {
    echo "Featured item for Ishkode already exists.\n";
    exit(0);
}

$now = time();
$oneMonthFromNow = $now + (30 * 24 * 60 * 60);

$featured = $featuredStorage->create([
    'entity_type' => 'page',
    'entity_id' => 0,
    'headline' => 'Ishkode — Word Game',
    'subheadline' => 'Learn Ojibwe through play. Guess words letter by letter and keep the campfire burning. Daily challenges, practice mode, and streaks.',
    'weight' => 5,
    'starts_at' => $now,
    'ends_at' => $oneMonthFromNow,
    'status' => 1,
    'link' => '/games/ishkode',
]);
$featuredStorage->save($featured);

echo "Created featured item for Ishkode (id: {$featured->id()})\n";
echo "Active from " . date('Y-m-d', $now) . " to " . date('Y-m-d', $oneMonthFromNow) . "\n";
