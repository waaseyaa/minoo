<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ingestion;

use App\Anokii\Pipeline\PipelineStage;
use App\Ingestion\Corpus\ReelFetcherInterface;
use App\Ingestion\Corpus\ReelFetchException;
use App\Ingestion\Corpus\ReelProcessor;
use App\Ingestion\Corpus\WhiteboardReaderInterface;
use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * ReelProcessor (#877): advances an ingested example_sentence to drafted by
 * running the ffmpeg + vision seams, with the orthography stored verbatim
 * (ADR 0003), idempotent re-runs, and a safe failure path.
 */
#[CoversClass(ReelProcessor::class)]
final class ReelProcessorTest extends HttpKernelTestCase
{
    private function seedIngested(string $corpusId): int
    {
        $repository = self::$kernel->getEntityTypeManager()->getRepository('example_sentence');
        $row = $repository->create([
            'ojibwe_text' => '',
            'english_text' => '',
            'source_sentence_id' => 'corpus:' . $corpusId,
            'pipeline_status' => PipelineStage::INGESTED,
            'consent_public' => 0,
            'status' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $repository->save($row);

        return (int) $row->id();
    }

    private function fakeFetcher(): ReelFetcherInterface
    {
        return new class () implements ReelFetcherInterface {
            public int $calls = 0;

            public function fetch(string $reelUrl, string $itemId): array
            {
                ++$this->calls;

                return ['keyframe' => '/tmp/' . $itemId . '.jpg', 'audio' => '/tmp/' . $itemId . '.opus'];
            }
        };
    }

    #[Test]
    public function it_advances_ingested_to_drafted_and_stores_verbatim(): void
    {
        $esid = $this->seedIngested('rp-1');
        $reader = new class () implements WhiteboardReaderInterface {
            public function read(string $keyframePath): array
            {
                // Leading/trailing spaces must survive — never normalized (ADR 0003).
                return ['ojibwe' => '  Zhooniyaans  ', 'english' => 'butter knife', 'notes' => 'vision'];
            }
        };

        $processor = new ReelProcessor(self::$kernel->getEntityTypeManager(), $this->fakeFetcher(), $reader);
        $result = $processor->process($esid);

        self::assertSame(PipelineStage::DRAFTED, $result['status']);
        self::assertNull($result['error']);

        $row = self::$kernel->getEntityTypeManager()->getRepository('example_sentence')->find((string) $esid);
        self::assertSame(PipelineStage::DRAFTED, (string) $row->get('pipeline_status'));
        self::assertSame('  Zhooniyaans  ', (string) $row->get('ojibwe_text'), 'Ojibwe must be verbatim.');
        self::assertSame('butter knife', (string) $row->get('english_text'));
        self::assertSame('/admin/anokii/media/thumb/rp-1', (string) $row->get('thumbnail_url'));
        self::assertSame('/admin/anokii/media/audio/rp-1', (string) $row->get('audio_url'));
    }

    #[Test]
    public function it_is_idempotent_and_does_not_reprocess_a_drafted_row(): void
    {
        $esid = $this->seedIngested('rp-2');
        $reader = new class () implements WhiteboardReaderInterface {
            public function read(string $keyframePath): array
            {
                return ['ojibwe' => 'A', 'english' => 'B', 'notes' => ''];
            }
        };
        $fetcher = $this->fakeFetcher();
        $processor = new ReelProcessor(self::$kernel->getEntityTypeManager(), $fetcher, $reader);

        $processor->process($esid);
        $second = $processor->process($esid);

        self::assertSame(PipelineStage::DRAFTED, $second['status']);
        self::assertSame(1, $fetcher->calls, 'A drafted row must not be fetched/processed again.');
    }

    #[Test]
    public function a_fetch_failure_keeps_the_row_ingested_and_records_the_error(): void
    {
        $esid = $this->seedIngested('rp-3');
        $failing = new class () implements ReelFetcherInterface {
            public function fetch(string $reelUrl, string $itemId): array
            {
                throw new ReelFetchException('ffmpeg exploded');
            }
        };
        $reader = new class () implements WhiteboardReaderInterface {
            public function read(string $keyframePath): array
            {
                return ['ojibwe' => '', 'english' => '', 'notes' => ''];
            }
        };

        $processor = new ReelProcessor(self::$kernel->getEntityTypeManager(), $failing, $reader);
        $result = $processor->process($esid);

        self::assertSame(PipelineStage::INGESTED, $result['status']);
        self::assertSame('ffmpeg exploded', $result['error']);

        $row = self::$kernel->getEntityTypeManager()->getRepository('example_sentence')->find((string) $esid);
        self::assertSame(PipelineStage::INGESTED, (string) $row->get('pipeline_status'));
        $prov = json_decode((string) $row->get('provenance'), true);
        self::assertSame('ffmpeg exploded', $prov['processing_error'] ?? null);
    }
}
