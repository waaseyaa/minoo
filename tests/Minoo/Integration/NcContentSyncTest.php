<?php

declare(strict_types=1);

namespace Minoo\Tests\Integration;

use Minoo\Ingestion\NcContentSyncService;
use Minoo\Support\NorthCloudClient;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

#[CoversNothing]
final class NcContentSyncTest extends TestCase
{
    private static string $projectRoot;
    private static HttpKernel $kernel;
    private static EntityTypeManager $etm;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__, 3);

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }

        putenv('WAASEYAA_DB=:memory:');

        self::$kernel = new HttpKernel(self::$projectRoot);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->invoke(self::$kernel);

        self::$etm = self::$kernel->getEntityTypeManager();
    }

    public static function tearDownAfterClass(): void
    {
        putenv('WAASEYAA_DB');

        $cachePath = self::$projectRoot . '/storage/framework/packages.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    private function createSyncService(string $responseJson): NcContentSyncService
    {
        $httpClient = static fn(string $url): string => $responseJson;
        $client = new NorthCloudClient(baseUrl: 'https://test.northcloud.one', httpClient: $httpClient);

        return new NcContentSyncService($client, self::$etm);
    }

    private function ncSearchResponse(array $hits): string
    {
        return json_encode([
            'hits' => $hits,
            'total_hits' => count($hits),
        ], JSON_THROW_ON_ERROR);
    }

    private function sampleArticleHit(string $title = 'Test Article', string $url = 'https://example.com/article-1', array $topics = ['indigenous']): array
    {
        return [
            'id' => 'doc-1',
            'title' => $title,
            'url' => $url,
            'source_name' => 'Test Source',
            'published_date' => '2026-03-20T12:00:00Z',
            'quality_score' => 85,
            'content_type' => 'article',
            'topics' => $topics,
            'snippet' => 'This is a test article about indigenous culture.',
        ];
    }

    #[Test]
    public function sync_creates_teaching_from_article_hit(): void
    {
        $json = $this->ncSearchResponse([$this->sampleArticleHit()]);
        $service = $this->createSyncService($json);

        $result = $service->sync(limit: 10);

        $this->assertSame(1, $result->created);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(0, $result->failed);

        // Verify the teaching was created
        $storage = self::$etm->getStorage('teaching');
        $ids = $storage->getQuery()->condition('source_url', 'https://example.com/article-1')->execute();
        $this->assertCount(1, $ids);

        $teaching = $storage->load(reset($ids));
        $this->assertSame('Test Article', $teaching->get('title'));
        $this->assertSame('external_link', $teaching->get('copyright_status'));
    }

    #[Test]
    public function sync_creates_event_from_event_topic_hit(): void
    {
        $hit = $this->sampleArticleHit('Community Gathering 2026', 'https://example.com/event-1', ['indigenous', 'event']);
        $json = $this->ncSearchResponse([$hit]);
        $service = $this->createSyncService($json);

        $result = $service->sync(limit: 10);

        $this->assertSame(1, $result->created);

        $storage = self::$etm->getStorage('event');
        $ids = $storage->getQuery()->condition('source_url', 'https://example.com/event-1')->execute();
        $this->assertCount(1, $ids);

        $event = $storage->load(reset($ids));
        $this->assertSame('Community Gathering 2026', $event->get('title'));
        $this->assertSame('gathering', $event->get('type'));
    }

    #[Test]
    public function sync_skips_duplicate_on_second_run(): void
    {
        $url = 'https://example.com/dedup-test-' . uniqid();
        $hit = $this->sampleArticleHit('Dedup Test', $url);
        $json = $this->ncSearchResponse([$hit]);

        // First run — creates
        $service1 = $this->createSyncService($json);
        $result1 = $service1->sync(limit: 10);
        $this->assertSame(1, $result1->created);

        // Second run — skips
        $service2 = $this->createSyncService($json);
        $result2 = $service2->sync(limit: 10);
        $this->assertSame(0, $result2->created);
        $this->assertSame(1, $result2->skipped);
    }

    #[Test]
    public function dry_run_creates_nothing(): void
    {
        $url = 'https://example.com/dryrun-' . uniqid();
        $hit = $this->sampleArticleHit('Dry Run Test', $url);
        $json = $this->ncSearchResponse([$hit]);
        $service = $this->createSyncService($json);

        $result = $service->sync(limit: 10, dryRun: true);

        $this->assertSame(1, $result->created);
        $this->assertSame(0, $result->skipped);

        // But nothing was actually persisted
        $storage = self::$etm->getStorage('teaching');
        $ids = $storage->getQuery()->condition('source_url', $url)->execute();
        $this->assertCount(0, $ids);
    }

    #[Test]
    public function sync_skips_hits_without_url(): void
    {
        $hit = $this->sampleArticleHit('No URL');
        unset($hit['url']);
        $json = $this->ncSearchResponse([$hit]);
        $service = $this->createSyncService($json);

        $result = $service->sync(limit: 10);

        $this->assertSame(0, $result->created);
        $this->assertSame(1, $result->failed);
    }

    #[Test]
    public function sync_handles_empty_response(): void
    {
        $json = $this->ncSearchResponse([]);
        $service = $this->createSyncService($json);

        $result = $service->sync(limit: 10);

        $this->assertSame(0, $result->created);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(0, $result->failed);
    }
}
