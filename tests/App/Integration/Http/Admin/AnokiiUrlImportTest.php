<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Anokii\Ingest\FetchResult;
use App\Http\Controller\Anokii\IngestController;
use App\Tests\Integration\Http\HttpKernelTestCase;
use App\Tests\Support\StubMediaFetcher;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * URL reel import in the Anokii Ingest workflow (#904). The MediaFetcher is
 * stubbed, so CI never touches Facebook. A successful fetch creates the same
 * ingested, Sagamok-tagged draft an upload would; every failure mode is a handled
 * response, never a 500.
 */
#[CoversNothing]
final class AnokiiUrlImportTest extends HttpKernelTestCase
{
    private static string $corpus;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$corpus = sys_get_temp_dir() . '/minoo-urlimport-' . bin2hex(random_bytes(4));
        putenv('MINOO_CORPUS_PATH=' . self::$corpus);
    }

    public static function tearDownAfterClass(): void
    {
        putenv('MINOO_CORPUS_PATH');
        parent::tearDownAfterClass();
    }

    #[Test]
    public function a_successful_fetch_creates_a_sagamok_tagged_awaiting_transcription_draft(): void
    {
        $response = $this->import('https://www.facebook.com/reel/123', new StubMediaFetcher());
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $body = $this->decode($response);
        self::assertTrue($body['ok']);
        self::assertSame('ingested', $body['pipeline_status']);

        $row = self::$kernel->getEntityTypeManager()->getStorage('example_sentence')->load((int) $body['esid']);
        self::assertNotNull($row);
        self::assertSame('oj-x-sagamok', (string) $row->get('language_tag'), 'Tagged at Sagamok community granularity.');
        self::assertSame('https://www.facebook.com/reel/123', (string) $row->get('source_url'), 'Source URL recorded for provenance.');
        self::assertSame('ingested', (string) $row->get('pipeline_status'));
        self::assertSame(0, (int) $row->get('status'), 'Awaiting transcription, not published.');

        // Staged exactly where uploads land.
        self::assertFileExists(self::$corpus . '/source-videos/' . $body['corpus_id'] . '.mp4');
    }

    #[Test]
    public function an_unsupported_host_is_a_handled_422(): void
    {
        $response = $this->import('https://evil.example.com/reel/9', new StubMediaFetcher());

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('Unsupported host', (string) $response->getContent());
    }

    #[Test]
    public function a_non_http_url_is_a_handled_422(): void
    {
        self::assertSame(422, $this->import('not-a-url', new StubMediaFetcher())->getStatusCode());
    }

    #[Test]
    public function an_unavailable_fetcher_is_handled_not_a_500(): void
    {
        $response = $this->import('https://youtu.be/abc123', new StubMediaFetcher(available: false));

        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('unavailable', (string) $response->getContent());
    }

    #[Test]
    public function a_failed_fetch_surfaces_the_reason_not_a_500(): void
    {
        $response = $this->import(
            'https://www.instagram.com/reel/xyz',
            new StubMediaFetcher(result: FetchResult::failure('That reel is private or login-walled and cannot be fetched.')),
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('private or login-walled', (string) $response->getContent());
    }

    private function import(string $url, StubMediaFetcher $fetcher): Response
    {
        $controller = new IngestController(
            self::$kernel->getEntityTypeManager(),
            SsrServiceProvider::getTwigEnvironment(),
            $fetcher,
        );

        $request = new HttpRequest();
        $request->initialize([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode(['url' => $url]));
        $account = $this->createMock(AccountInterface::class);

        return $controller->url([], [], $account, $request);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
