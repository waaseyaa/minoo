<?php

declare(strict_types=1);

/**
 * Corpus import (Phase 4: consent granted).
 *
 * Reads per-item JSON from a community-controlled corpus directory OUTSIDE this
 * repository and materializes language entities with full provenance.
 *
 * CONSENT STATE: written consent has been granted by the speaker (Steven
 * Bennett) for public display, publication, search, AI grounding, and AI
 * training. Rows are therefore imported consent-public, AI-training-consented,
 * and published. Before this grant the importer wrote every row with consent
 * OFF + status 0; see git history. To return to the gated state, set the
 * CONSENT_* / STATUS constants below back to 0.
 *
 * HARD REQUIREMENTS (do not weaken):
 *   - Orthography is recorded EXACTLY as the speaker wrote it. Never correct.
 *   - Nothing from the corpus directory is ever copied into the repo. Audio,
 *     video, and images stay in the source directory; only provenance paths and
 *     a served audio URL (gated by the consent query) are recorded.
 *
 * Idempotent: items dedup on example_sentence.source_sentence_id and speakers
 * dedup on speaker.code.
 *
 * Usage: php scripts/import-corpus.php <corpus-dir> [--dry-run]
 */

use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

require dirname(__DIR__) . '/vendor/autoload.php';

// Phase 4 consent grant. All three ON per the speaker's written consent.
const CONSENT_PUBLIC = 1;
const CONSENT_AI_TRAINING = 1;
const STATUS_PUBLISHED = 1;

// Credit: Steven Bennett's public profile, the source of every corpus reel.
const STEVEN_PROFILE_URL = 'https://www.facebook.com/profile.php?id=61582894730998';

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
 * Find or create the speaker for a contributor name, consent granted.
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
        echo "[dry-run] would create speaker: $name ($code), consent granted, published\n";
        return $cache[$code] = null;
    }

    $speaker = $storage->create([
        'name' => $name,
        'code' => $code,
        'slug' => strtolower(str_replace(' ', '-', trim($name))),
        'bio' => sprintf('%s contributed this Anishinaabemowin corpus from public videos. Credit and source: %s', $name, STEVEN_PROFILE_URL),
        // Consent granted (Phase 4).
        'consent_public_display' => CONSENT_PUBLIC,
        'consent_ai_training' => CONSENT_AI_TRAINING,
        'status' => STATUS_PUBLISHED,
        'created_at' => time(),
        'updated_at' => time(),
    ]);
    $storage->save($speaker);
    echo "[create] speaker: $name ($code) id=" . $speaker->id() . " consent granted, published\n";

    return $cache[$code] = (int) $speaker->id();
}

/**
 * Media fields derived from a corpus item (#852). A thumbnail exists for every
 * item; its URL points at the consent-gated CorpusImageController route, never
 * at the external source. Context-image credit/source/article metadata is kept
 * for attribution.
 *
 * A web-optimized teaching reel (#873) is set only when the transcoded file
 * exists at <corpus>/video/<id>.mp4, so items without a video fall back to the
 * thumbnail + audio.
 *
 * @param array<string, mixed> $item
 *
 * @return array<string, string>
 */
function corpusMediaFields(array $item, string $corpusDir): array
{
    $id = (string) ($item['id'] ?? '');
    $hasVideo = $id !== '' && is_file(rtrim($corpusDir, '/\\') . '/video/' . $id . '.mp4');

    return [
        'video_url' => $hasVideo ? '/lessons/media/video/' . $id : '',
        'thumbnail_url' => $id !== '' ? '/media/corpus/thumb/' . $id : '',
        'context_image_credit' => (string) ($item['context_image_credit'] ?? ''),
        'context_image_source' => (string) ($item['context_image_source'] ?? ''),
        'context_image_article' => (string) ($item['context_image_article'] ?? ''),
    ];
}

$files = glob($itemsDir . DIRECTORY_SEPARATOR . '*.json');
sort($files);

$created = 0;
$skipped = 0;
$backfilled = 0;
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
        // Already imported: backfill the #852 / #873 media fields idempotently.
        // Only empty fields are written, so re-running never clobbers values.
        $media = corpusMediaFields($item, $corpusDir);

        if ($dryRun) {
            echo "[dry-run] would backfill media fields on {$item['id']}\n";
            $skipped++;
            continue;
        }

        $entity = $sentenceStorage->load(reset($existing));
        if ($entity === null) {
            $skipped++;
            continue;
        }

        $changed = false;
        foreach ($media as $field => $value) {
            if ($value !== '' && (string) ($entity->get($field) ?? '') === '') {
                $entity->set($field, $value);
                $changed = true;
            }
        }

        if ($changed) {
            $entity->set('updated_at', time());
            $sentenceStorage->save($entity);
            $backfilled++;
            echo "[backfill] {$item['id']} -> example_sentence " . $entity->id() . " (media fields)\n";
        } else {
            $skipped++;
        }
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
        // Audio stays in the community-controlled corpus directory and is served
        // by CorpusAudioController, which re-checks the consent gate per request.
        'audio_url' => '/media/corpus/audio/' . $item['id'],
        // Video (#873) + thumbnail + context image, served from the corpus
        // directory by the consent-gated media controllers.
        ...corpusMediaFields($item, $corpusDir),
        'source_sentence_id' => $sourceId,
        'source_url' => (string) ($item['source_url'] ?? ''),
        'source_date' => (string) ($item['source_date'] ?? ''),
        'provenance' => json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'language_code' => 'oj',
        // Consent granted (Phase 4).
        'consent_public' => CONSENT_PUBLIC,
        'consent_ai_training' => CONSENT_AI_TRAINING,
        'status' => STATUS_PUBLISHED,
        'created_at' => time(),
        'updated_at' => time(),
    ]);
    $sentenceStorage->save($sentence);
    $created++;
    echo "[import] {$item['id']} -> example_sentence " . $sentence->id() . " (consent granted, published)\n";
}

echo "\nDone. created=$created backfilled=$backfilled skipped=$skipped failed=$failed\n";
exit($failed > 0 ? 1 : 0);
