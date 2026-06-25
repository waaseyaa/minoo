#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Dev generator (issue #906): turn the 2026-06-25 recon output into the committed
 * translation-backlog seed dataset.
 *
 * Reads the recon ranked output (default: the recon scratch dir, NOT in this
 * repo), applies App\Language\Backlog\BacklogBuilder, and writes
 * config/translation_backlog_seed.json. Run once on a machine that has the recon
 * output; the resulting JSON is committed so seeding is reproducible without the
 * raw crawl. The seeder never re-derives - it only reads the committed file.
 *
 * Run: php scripts/build_translation_backlog_seed.php [path/to/out.json]
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Language\Backlog\BacklogBuilder;

$reconPath = $argv[1] ?? 'C:/Users/jones/minoo-recon/out.json';
if (!is_file($reconPath)) {
    fwrite(STDERR, "Recon output not found: {$reconPath}\n");
    exit(1);
}

$recon = json_decode((string) file_get_contents($reconPath), true);
if (!is_array($recon) || !isset($recon['ranked']) || !is_array($recon['ranked'])) {
    fwrite(STDERR, "Malformed recon output (no 'ranked' array): {$reconPath}\n");
    exit(1);
}

$built = (new BacklogBuilder())->build($recon['ranked']);

$out = [
    'provenance' => 'seed-crawl-2026-06-25',
    'generated_from' => 'recon out.json (' . count($recon['ranked']) . ' distinct, >= 2 sites)',
    'recon_report' => 'Projects/Minoo/docs/translation-seed-crawler-recon-2026-06-25.md',
    'stats' => $built['stats'],
    'excluded_anishinaabemowin' => $built['excluded_anishinaabemowin'],
    'rows' => $built['rows'],
];

$dest = dirname(__DIR__) . '/config/translation_backlog_seed.json';
file_put_contents($dest, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

fwrite(STDERR, sprintf(
    "Wrote %s\n  kept=%d  below_floor=%d  dropped_noise=%d  excluded_ojibwe=%d\n  excluded: %s\n",
    $dest,
    $built['stats']['kept'],
    $built['stats']['below_floor'],
    $built['stats']['dropped_noise'],
    $built['stats']['excluded_ojibwe'],
    implode(', ', $built['excluded_anishinaabemowin']),
));
