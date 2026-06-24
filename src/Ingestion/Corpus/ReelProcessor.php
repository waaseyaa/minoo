<?php

declare(strict_types=1);

namespace App\Ingestion\Corpus;

use App\Anokii\Pipeline\PipelineStage;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Processes one staged reel for the Anokii ingest pipeline (#877): runs the
 * {@see ReelFetcherInterface} (ffmpeg keyframe + opus) and the
 * {@see WhiteboardReaderInterface} vision draft, then advances the
 * example_sentence row from `ingested` to `drafted`.
 *
 * This is the work the async worker (`anokii:process-reels`) does per row. It is
 * a plain DI service — NOT a framework Queue Job — because a deserialized Job
 * cannot resolve EntityTypeManager at handle() time. The same service backs the
 * console worker and is unit-tested directly with fake seams.
 *
 * Idempotent: a row not at `ingested` is returned unchanged. Degrades safely:
 * if the vision draft is unavailable (no ANTHROPIC_API_KEY -> NullLlmProvider),
 * the row still advances to `drafted` with blank text for a human to complete.
 * Ojibwe is stored EXACTLY as read — never normalized (ADR 0003).
 */
final class ReelProcessor
{
    /** Staff-gated media routes (consent-gated public routes hide drafts). */
    private const string MEDIA_BASE = '/admin/anokii/media';

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly ReelFetcherInterface $fetcher,
        private readonly WhiteboardReaderInterface $reader,
    ) {
    }

    /**
     * @return array{esid: int, status: string, error: string|null}
     */
    public function process(int $esid, bool $skipVision = false): array
    {
        $storage = $this->entityTypeManager->getStorage('example_sentence');
        $row = $storage->load($esid);
        if (!$row instanceof EntityInterface) {
            return ['esid' => $esid, 'status' => 'missing', 'error' => 'Row not found.'];
        }

        $stage = (string) ($row->get('pipeline_status') ?? '');
        if ($stage !== PipelineStage::INGESTED) {
            // Already processed (or beyond) — nothing to do.
            return ['esid' => $esid, 'status' => $stage !== '' ? $stage : PipelineStage::INGESTED, 'error' => null];
        }

        $reelId = str_replace('corpus:', '', (string) $row->get('source_sentence_id'));

        try {
            $media = $this->fetcher->fetch((string) $row->get('source_url'), $reelId);

            $draft = $skipVision
                ? ['ojibwe' => '', 'english' => '', 'notes' => 'skip_vision']
                : $this->reader->read($media['keyframe']);

            // Verbatim — orthography is never normalized (ADR 0003).
            $row->set('ojibwe_text', $draft['ojibwe']);
            $row->set('english_text', $draft['english']);
            $row->set('notes', $draft['notes']);
            $row->set('thumbnail_url', self::MEDIA_BASE . '/thumb/' . $reelId);
            $row->set('audio_url', self::MEDIA_BASE . '/audio/' . $reelId);
            $row->set('provenance', $this->provenance($row, $reelId, $media, $draft['notes'], null));
            $row->set('pipeline_status', PipelineStage::DRAFTED);
            $row->set('updated_at', time());
            $storage->save($row);

            return ['esid' => $esid, 'status' => PipelineStage::DRAFTED, 'error' => null];
        } catch (\Throwable $e) {
            // Keep the row at `ingested` so it can be retried; record the error in
            // provenance (a JSON blob — never the transcriber `notes`) so the
            // ingest UI can surface it.
            $row->set('provenance', $this->provenance($row, $reelId, null, '', $e->getMessage()));
            $row->set('updated_at', time());
            $storage->save($row);

            return ['esid' => $esid, 'status' => PipelineStage::INGESTED, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array{keyframe: string, audio: string}|null $media
     */
    private function provenance(EntityInterface $row, string $reelId, ?array $media, string $visionNotes, ?string $error): string
    {
        $existing = json_decode((string) ($row->get('provenance') ?? ''), true);
        $data = is_array($existing) ? $existing : [];

        $data['id'] = $reelId;
        $data['source_url'] = (string) $row->get('source_url');
        if ($media !== null) {
            $data['audio_path'] = $media['audio'];
            $data['thumbnail_path'] = $media['keyframe'];
            $data['vision_notes'] = $visionNotes;
            unset($data['processing_error']);
        }
        if ($error !== null) {
            $data['processing_error'] = $error;
        }

        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
