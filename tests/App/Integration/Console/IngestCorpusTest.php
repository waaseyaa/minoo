<?php

declare(strict_types=1);

namespace App\Tests\Integration\Console;

use App\Ingestion\Corpus\CorpusIngestor;
use App\Ingestion\Corpus\IngestedReel;
use App\Ingestion\Corpus\ReelFetcherInterface;
use App\Ingestion\Corpus\ReelFetchException;
use App\Ingestion\Corpus\WhiteboardReaderInterface;
use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * `ingest:corpus` core (#854): the orchestration writes example_sentence +
 * speaker entities directly (network + LLM mocked), as unreviewed drafts;
 * --dry-run writes nothing; --skip-vision leaves the gloss blank; re-runs dedup.
 *
 * The live Facebook pull + vision LLM are NOT exercised here (no creds/network);
 * the ReelFetcher and WhiteboardReader are faked behind their interfaces. Boots a
 * real kernel (in-memory SQLite) for genuine entity storage.
 */
#[CoversClass(CorpusIngestor::class)]
final class IngestCorpusTest extends HttpKernelTestCase
{
    private function etm(): EntityTypeManager
    {
        return self::$kernel->getEntityTypeManager();
    }

    private function ingestor(ReelFetcherInterface $fetcher, WhiteboardReaderInterface $reader): CorpusIngestor
    {
        return new CorpusIngestor($this->etm(), $fetcher, $reader);
    }

    private function noop(): callable
    {
        return static function (string $line): void {
        };
    }

    /** @return list<int> example_sentence ids matching the given corpus ids */
    private function sentenceIds(string $likeId): array
    {
        return $this->etm()->getRepository('example_sentence')->getQuery()->accessCheck(false)
            ->condition('source_sentence_id', $likeId, 'LIKE')
            ->execute();
    }

    #[Test]
    public function ingest_writes_draft_entities_with_mocked_pipeline(): void
    {
        $fetcher = new FakeReelFetcher();
        $reader = new FakeWhiteboardReader(['ojibwe' => 'Zhooniyaans', 'english' => 'coin', 'notes' => 'high']);

        $result = $this->ingestor($fetcher, $reader)->ingest(
            [new IngestedReel('sb-901', 'https://fb/reel/901', '2026-06-20'), new IngestedReel('sb-902', 'https://fb/reel/902')],
            dryRun: false,
            skipVision: false,
            limit: null,
            log: $this->noop(),
        );

        self::assertSame(2, $result->created);
        self::assertSame(0, $result->failed);

        $ids = $this->sentenceIds('corpus:sb-90%');
        self::assertCount(2, $ids);

        $entity = $this->etm()->getRepository('example_sentence')->find((string) reset($ids));
        self::assertSame('Zhooniyaans', (string) $entity->get('ojibwe_text'));
        self::assertSame('coin', (string) $entity->get('english_text'));
        self::assertSame('/media/corpus/audio/sb-901', (string) $entity->get('audio_url'));
        self::assertSame('/media/corpus/thumb/sb-901', (string) $entity->get('thumbnail_url'));
        self::assertSame('https://fb/reel/901', (string) $entity->get('source_url'));
        // Unreviewed draft: off public surfaces.
        self::assertSame(0, (int) $entity->get('status'));
        self::assertSame(0, (int) $entity->get('consent_public'));

        // Exactly one speaker (Steven Bennett, deduped) across both rows.
        $speakers = $this->etm()->getRepository('speaker')->getQuery()->accessCheck(false)->condition('code', 'sb')->execute();
        self::assertCount(1, $speakers);
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        $result = $this->ingestor(new FakeReelFetcher(), new FakeWhiteboardReader([]))->ingest(
            [new IngestedReel('sb-911', 'https://fb/reel/911')],
            dryRun: true,
            skipVision: false,
            limit: null,
            log: $this->noop(),
        );

        self::assertSame(0, $result->created);
        self::assertSame([], $this->sentenceIds('corpus:sb-911'));
    }

    #[Test]
    public function skip_vision_leaves_the_gloss_blank(): void
    {
        // Reader would return text, but --skip-vision must bypass it entirely.
        $result = $this->ingestor(new FakeReelFetcher(), new FakeWhiteboardReader(['ojibwe' => 'X', 'english' => 'Y']))->ingest(
            [new IngestedReel('sb-921', 'https://fb/reel/921')],
            dryRun: false,
            skipVision: true,
            limit: null,
            log: $this->noop(),
        );

        self::assertSame(1, $result->created);
        $ids = $this->sentenceIds('corpus:sb-921');
        $entity = $this->etm()->getRepository('example_sentence')->find((string) reset($ids));
        self::assertSame('', (string) $entity->get('ojibwe_text'));
        self::assertSame('', (string) $entity->get('english_text'));
        self::assertSame('skip_vision', (string) $entity->get('notes'));
    }

    #[Test]
    public function re_running_dedups_on_source_sentence_id(): void
    {
        $reels = [new IngestedReel('sb-931', 'https://fb/reel/931')];
        $first = $this->ingestor(new FakeReelFetcher(), new FakeWhiteboardReader(['ojibwe' => 'a', 'english' => 'b']))->ingest($reels, false, false, null, $this->noop());
        $second = $this->ingestor(new FakeReelFetcher(), new FakeWhiteboardReader(['ojibwe' => 'a', 'english' => 'b']))->ingest($reels, false, false, null, $this->noop());

        self::assertSame(1, $first->created);
        self::assertSame(0, $second->created);
        self::assertSame(1, $second->skipped);
        self::assertCount(1, $this->sentenceIds('corpus:sb-931'));
    }

    #[Test]
    public function a_fetch_failure_is_counted_and_does_not_abort_the_run(): void
    {
        $fetcher = new FakeReelFetcher(failIds: ['sb-941']);
        $result = $this->ingestor($fetcher, new FakeWhiteboardReader(['ojibwe' => 'ok', 'english' => 'ok']))->ingest(
            [new IngestedReel('sb-941', 'https://fb/reel/941'), new IngestedReel('sb-942', 'https://fb/reel/942')],
            dryRun: false,
            skipVision: false,
            limit: null,
            log: $this->noop(),
        );

        self::assertSame(1, $result->created);
        self::assertSame(1, $result->failed);
        self::assertSame([], $this->sentenceIds('corpus:sb-941'));
        self::assertCount(1, $this->sentenceIds('corpus:sb-942'));
    }
}

/** Fake fetcher: writes throwaway media to a temp dir, or throws for designated ids. */
final class FakeReelFetcher implements ReelFetcherInterface
{
    /** @param list<string> $failIds */
    public function __construct(private readonly array $failIds = [])
    {
    }

    public function fetch(string $reelUrl, string $itemId): array
    {
        if (in_array($itemId, $this->failIds, true)) {
            throw new ReelFetchException("mock fetch failure for {$itemId}");
        }

        $dir = sys_get_temp_dir() . '/minoo_ingest_test';
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        $keyframe = $dir . '/' . $itemId . '.jpg';
        $audio = $dir . '/' . $itemId . '.opus';
        file_put_contents($keyframe, 'jpg');
        file_put_contents($audio, 'opus');

        return ['keyframe' => $keyframe, 'audio' => $audio];
    }
}

/** Fake reader: returns a fixed draft (no LLM). */
final class FakeWhiteboardReader implements WhiteboardReaderInterface
{
    /** @param array<string, string> $draft */
    public function __construct(private readonly array $draft)
    {
    }

    public function read(string $keyframePath): array
    {
        return [
            'ojibwe' => $this->draft['ojibwe'] ?? '',
            'english' => $this->draft['english'] ?? '',
            'notes' => $this->draft['notes'] ?? '',
        ];
    }
}
