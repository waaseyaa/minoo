#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Seed the translation backlog (tm_backlog) on production (issue #906).
 *
 * Production's ConsoleKernel is broken (#493), so this boots the HttpKernel via
 * reflection (the scripts/populate_featured.php pattern) and runs the same
 * idempotent {@see App\Seed\TranslationBacklogSeeder}. Re-runnable.
 *
 * Run: php scripts/seed_translation_backlog.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Seed\TranslationBacklogData;
use App\Seed\TranslationBacklogSeeder;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

$projectRoot = dirname(__DIR__);
$kernel = new HttpKernel($projectRoot);
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);

$seeder = new TranslationBacklogSeeder($kernel->getEntityTypeManager());
$rows = TranslationBacklogData::load($projectRoot);
$result = $seeder->seed($rows);

printf(
    "tm_backlog seeded: %d created, %d updated, %d total.\n",
    $result['created'],
    $result['updated'],
    $result['total'],
);
foreach ($result['by_category'] as $category => $count) {
    printf("  %-14s %d\n", $category, $count);
}
