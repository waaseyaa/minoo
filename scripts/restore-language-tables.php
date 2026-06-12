<?php

declare(strict_types=1);

/**
 * Phase 3 one-off for the production restore.
 *
 * 1. Creates the language-domain tables that never existed in the
 *    2026-05-20 production backup (speaker, word_part, contributor,
 *    dialect_region) - same SQL as
 *    migrations/20260612_160000_create_language_domain_tables.php.
 * 2. Backfills waaseyaa_migrations records for every migration file in
 *    migrations/ under both discovery namespaces ("app" and
 *    "waaseyaa/scheduler"). The restored database predates consistent
 *    migration bookkeeping: its tables exist but most records are
 *    missing, so a blind `bin/waaseyaa migrate` would re-run unguarded
 *    ALTERs. After this backfill, migrate only ever runs genuinely new
 *    files.
 *
 * Idempotent. Run with: php scripts/restore-language-tables.php
 */

$projectRoot = dirname(__DIR__);
$dbPath = getenv('WAASEYAA_DB') ?: $projectRoot . '/storage/waaseyaa.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- 1. Tables -------------------------------------------------------------

$tables = [
    'speaker' => 'CREATE TABLE speaker (
        spid INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid CLOB, bundle CLOB, name CLOB, langcode CLOB, _data CLOB
    )',
    'word_part' => 'CREATE TABLE word_part (
        wpid INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid CLOB, bundle CLOB, form CLOB, langcode CLOB, _data CLOB
    )',
    'contributor' => 'CREATE TABLE contributor (
        coid INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid CLOB, bundle CLOB, name CLOB, langcode CLOB, _data CLOB
    )',
    'dialect_region' => 'CREATE TABLE dialect_region (
        code TEXT PRIMARY KEY,
        bundle CLOB, langcode CLOB, _data CLOB
    )',
];

foreach ($tables as $name => $sql) {
    $exists = $db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name=" . $db->quote($name))->fetchColumn();
    if ($exists) {
        echo "[skip] table $name exists\n";
        continue;
    }
    $db->exec($sql);
    echo "[create] table $name\n";
}

// --- 2. Migration record backfill -------------------------------------------

$existing = $db->query('SELECT migration FROM waaseyaa_migrations')->fetchAll(PDO::FETCH_COLUMN);
$existing = array_flip($existing);

$batch = (int) $db->query('SELECT COALESCE(MAX(batch), 0) FROM waaseyaa_migrations')->fetchColumn() + 1;
$insert = $db->prepare('INSERT INTO waaseyaa_migrations (migration, package, batch) VALUES (?, ?, ?)');

$files = glob($projectRoot . '/migrations/*.php');
sort($files);
$added = 0;

// The migration registry's package attribution for the app migrations dir is
// unstable across boots (alpha.208 discovery quirk: a different waaseyaa
// package claims migrations/ each run). Record under every candidate
// namespace so `migrate` is a no-op regardless of attribution.
$packages = ['app'];
foreach (glob($projectRoot . '/vendor/waaseyaa/*', GLOB_ONLYDIR) as $dir) {
    $packages[] = 'waaseyaa/' . basename($dir);
}

foreach ($files as $file) {
    $stem = basename($file, '.php');
    foreach ($packages as $package) {
        $key = $package . ':' . $stem;
        if (isset($existing[$key])) {
            continue;
        }
        $insert->execute([$key, $package, $batch]);
        $added++;
        echo "[record] $key\n";
    }
}

echo "\nDone. $added migration records backfilled (batch $batch).\n";
