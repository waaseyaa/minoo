<?php

declare(strict_types=1);

/**
 * Consent-gated corpus import (Phase 4).
 *
 * Reads per-item JSON from a community-controlled corpus directory OUTSIDE
 * this repository and materializes language entities with full provenance.
 *
 * HARD REQUIREMENTS (do not weaken):
 *   - Every imported row gets ALL consent flags OFF and status 0
 *     (unpublished). They are visible to admins only and excluded from
 *     search and any AI grounding until written consent records exist.
 *   - Nothing from the corpus directory is ever copied into the repo.
 *     Audio/video stay in the source directory; only provenance paths are
 *     recorded.
 *
 * Idempotent: items dedup on example_sentence.source_sentence_id and
 * speakers dedup on speaker.code.
 *
 * Usage: php scripts/import-corpus.php <corpus-dir> [--dry-run]
 */

use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__);

if (is_file($projectRoot . '/.env')) {
    (new Symfony\Component\Dotenv\Dotenv())->usePutenv()->load($projectRoot . '/.env');
}

$corpusDir = $argv[1] ?? '';
$dryRun = in_array('--dry-run', $argv, true);

if ($corpusDir === '' || !is_dir($corpusDir)) {
    fwrite(STDERR, "Usage: php scripts/import-corpus.php <corpus-dir> [--dry-run]\n");
    exit(1);
}

$itemsDir = rtrim($corpusDir, '/\\') . DIRECTORY_SEPARATOR . 'items';
if (!is_dir($itemsDir)) {
    fwrite(STDERR, "No items/ directory under $corpusDir\n");
    exit(1);
}

$kernel = new HttpKernel($projectRoot);
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);
$etm = $kernel->getEntityTypeManager();

$speakerStorage = $etm->getStorage('speaker');
$sentenceStorage = $etm->getStorage('example_sentence');

/**
 * Find or create the speaker for a contributor name, consent flags OFF.
 */
function resolveSpeaker(object $storage, string $name, bool $dryRun): ?int
{
    static $cache = [];

    $code = strtolower(implode('', array_map(
        static fn (string $part): string => mb_substr(trim($part), 0, 1),
        array_filter(explode(' ', $name)),
    )));

    if (isset($cache[$code])) {
        return $cache[$code];
    }

    $ids = $storage->getQuery()->accessCheck(false)->condition('code', $code)->execute();
    if ($ids !== []) {
        return $cache[$code] = (int) reset($ids);
    }

    if ($dryRun) {
        echo "[dry-run] would create speaker: $name ($code), consent OFF, unpublished\n";
        return $cache[$code] = null;
    }

    $speaker = $storage->create([
        'name' => $name,
        'code' => $code,
        'slug' => strtolower(str_replace(' ', '-', trim($name))),
        // Consent gate: OFF until a written consent record exists.
        'consent_public_display' => 0,
        'consent_ai_training' => 0,
        'status' => 0,
        'created_at' => time(),
        'updated_at' => time(),
    ]);
    $storage->save($speaker);
    echo "[create] speaker: $name ($code) id=" . $speaker->id() . " consent OFF, unpublished\n";

    return $cache[$code] = (int) $speaker->id();
}

$files = glob($itemsDir . DIRECTORY_SEPARATOR . '*.json');
sort($files);

$created = 0;
$skipped = 0;
$failed = 0;

foreach ($files as $file) {
    $item = json_decode((string) file_get_contents($file), true);
    if (!is_array($item) || ($item['id'] ?? '') === '' || ($item['ojibwe'] ?? '') === '') {
        echo '[fail] unreadable or incomplete item: ' . basename($file) . "\n";
        $failed++;
        continue;
    }

    $sourceId = 'corpus:' . $item['id'];

    $existing = $sentenceStorage->getQuery()->accessCheck(false)
        ->condition('source_sentence_id', $sourceId)
        ->execute();
    if ($existing !== []) {
        $skipped++;
        continue;
    }

    $speakerId = resolveSpeaker($speakerStorage, (string) ($item['contributor'] ?? 'Unknown'), $dryRun);

    if ($dryRun) {
        echo "[dry-run] would import {$item['id']}: {$item['ojibwe']}\n";
        continue;
    }

    // Orthography is recorded EXACTLY as the speaker wrote it. Never correct.
    $sentence = $sentenceStorage->create([
        'ojibwe_text' => (string) $item['ojibwe'],
        'english_text' => (string) ($item['english'] ?? ''),
        'speaker_id' => $speakerId,
        // Audio stays in the community-controlled corpus directory; no URL
        // is published. The provenance record carries the source paths.
        'audio_url' => '',
        'source_sentence_id' => $sourceId,
        'source_url' => (string) ($item['source_url'] ?? ''),
        'source_date' => (string) ($item['source_date'] ?? ''),
        'provenance' => json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'language_code' => 'oj',
        // Consent gate: OFF + unpublished until written consent exists.
        'consent_public' => 0,
        'consent_ai_training' => 0,
        'status' => 0,
        'created_at' => time(),
        'updated_at' => time(),
    ]);
    $sentenceStorage->save($sentence);
    $created++;
    echo "[import] {$item['id']} -> example_sentence " . $sentence->id() . " (consent OFF, unpublished)\n";
}

echo "\nDone. created=$created skipped=$skipped failed=$failed\n";
exit($failed > 0 ? 1 : 0);
