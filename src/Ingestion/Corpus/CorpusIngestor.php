<?php

declare(strict_types=1);

namespace App\Ingestion\Corpus;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Corpus ingest orchestration (#854) — the PHP port of code/ingest/ingest.py's
 * per-reel pipeline, writing Minoo entities directly instead of JSON files.
 *
 * For each reel: fetch media (keyframe + opus) via {@see ReelFetcherInterface},
 * draft the gloss via {@see WhiteboardReaderInterface} (unless --skip-vision),
 * then materialize a `speaker` (deduped) and an `example_sentence` directly into
 * entity storage. Entities are the system of record — no items/*.json is written.
 *
 * Newly ingested rows are unreviewed DRAFTS: status 0 and consent OFF, so they
 * never reach public surfaces (which gate on status 1 + consent) until a
 * reviewer transcribes/publishes them via the Anokii transcribe dashboard (#853).
 *
 * Idempotent: a reel whose `corpus:<id>` example_sentence already exists is
 * skipped. The Ojibwe is stored EXACTLY as read — never normalized (ADR 0003).
 */
final class CorpusIngestor
{
    /** Credit: Steven Bennett's public profile, the source of every corpus reel. */
    private const string STEVEN_PROFILE_URL = 'https://www.facebook.com/profile.php?id=61582894730998';

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly ReelFetcherInterface $fetcher,
        private readonly WhiteboardReaderInterface $reader,
    ) {
    }

    /**
     * @param list<IngestedReel> $reels
     * @param callable(string): void $log
     */
    public function ingest(array $reels, bool $dryRun, bool $skipVision, ?int $limit, callable $log): IngestResult
    {
        $sentences = $this->entityTypeManager->getStorage('example_sentence');
        $speakers = $this->entityTypeManager->getStorage('speaker');

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $processed = 0;

        foreach ($reels as $reel) {
            if ($limit !== null && $processed >= $limit) {
                break;
            }
            ++$processed;

            $sourceId = 'corpus:' . $reel->id;

            $existing = $sentences->getQuery()->accessCheck(false)
                ->condition('source_sentence_id', $sourceId)
                ->range(0, 1)
                ->execute();
            if ($existing !== []) {
                $log("[skip] {$reel->id} already ingested");
                ++$skipped;
                continue;
            }

            if ($dryRun) {
                $log("[dry-run] would ingest {$reel->id} from {$reel->url}");
                continue;
            }

            try {
                $media = $this->fetcher->fetch($reel->url, $reel->id);

                $draft = $skipVision
                    ? ['ojibwe' => '', 'english' => '', 'notes' => 'skip_vision']
                    : $this->reader->read($media['keyframe']);

                $speakerId = $this->resolveSpeaker($speakers, $reel->contributor);

                $entity = $sentences->create([
                    // Stored verbatim — orthography is never normalized (ADR 0003).
                    'ojibwe_text' => $draft['ojibwe'],
                    'english_text' => $draft['english'],
                    'notes' => $draft['notes'],
                    'speaker_id' => $speakerId,
                    'audio_url' => '/media/corpus/audio/' . $reel->id,
                    'thumbnail_url' => '/media/corpus/thumb/' . $reel->id,
                    'source_sentence_id' => $sourceId,
                    'source_url' => $reel->url,
                    'source_date' => $reel->sourceDate,
                    'provenance' => (string) json_encode([
                        'id' => $reel->id,
                        'source_url' => $reel->url,
                        'source_date' => $reel->sourceDate,
                        'contributor' => $reel->contributor,
                        'audio_path' => $media['audio'],
                        'thumbnail_path' => $media['keyframe'],
                        'vision_notes' => $draft['notes'],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'language_code' => 'oj',
                    // Unreviewed draft: off all public surfaces until published.
                    'consent_public' => 0,
                    'consent_ai_training' => 0,
                    'status' => 0,
                    'created_at' => time(),
                    'updated_at' => time(),
                ]);
                $sentences->save($entity);
                $log("[ingest] {$reel->id} -> example_sentence " . $entity->id() . ' (draft)');
                ++$created;
            } catch (\Throwable $e) {
                $log("[fail] {$reel->id}: " . $e->getMessage());
                ++$failed;
            }
        }

        return new IngestResult($created, $skipped, $failed);
    }

    /**
     * Find or create the speaker for a contributor name (deduped on initials),
     * mirroring scripts/import-corpus.php.
     */
    private function resolveSpeaker(object $speakers, string $name): ?int
    {
        $code = strtolower(implode('', array_map(
            static fn (string $part): string => mb_substr(trim($part), 0, 1),
            array_filter(explode(' ', $name)),
        )));

        $ids = $speakers->getQuery()->accessCheck(false)->condition('code', $code)->range(0, 1)->execute();
        if ($ids !== []) {
            return (int) reset($ids);
        }

        $speaker = $speakers->create([
            'name' => $name,
            'code' => $code,
            'slug' => strtolower(str_replace(' ', '-', trim($name))),
            'bio' => sprintf('%s contributed this Anishinaabemowin corpus from public videos. Credit and source: %s', $name, self::STEVEN_PROFILE_URL),
            'consent_public_display' => 1,
            'consent_ai_training' => 1,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $speakers->save($speaker);

        return $speaker instanceof EntityInterface ? (int) $speaker->id() : null;
    }
}
