<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Language;

use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corpus image routes (#852): thumbnail + context image are streamed from
 * MINOO_CORPUS_PATH for a consent_public + published example_sentence, and are
 * denied (404) otherwise — even when the file exists on disk. Also confirms the
 * new media fields round-trip on the entity (so the importer can write them).
 */
#[CoversNothing]
final class CorpusImageRouteTest extends HttpKernelTestCase
{
    private static string $corpusDir = '';
    private static ?string $previousCorpusPath = null;
    private const string THUMB_BYTES = 'FAKE-JPEG-THUMB';
    private const string CONTEXT_BYTES = 'FAKE-JPEG-CONTEXT';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Temp corpus dir so the test never depends on the real media directory.
        self::$corpusDir = sys_get_temp_dir() . '/minoo_corpus_test_' . getmypid();
        @mkdir(self::$corpusDir . '/thumbs', 0o777, true);
        @mkdir(self::$corpusDir . '/context-images', 0o777, true);
        file_put_contents(self::$corpusDir . '/thumbs/sb-consent.jpg', self::THUMB_BYTES);
        file_put_contents(self::$corpusDir . '/context-images/sb-consent.jpg', self::CONTEXT_BYTES);
        // A private item whose file exists on disk but must stay gated.
        file_put_contents(self::$corpusDir . '/thumbs/sb-private.jpg', 'SHOULD-NOT-BE-SERVED');

        $prev = getenv('MINOO_CORPUS_PATH');
        self::$previousCorpusPath = $prev === false ? null : $prev;
        putenv('MINOO_CORPUS_PATH=' . self::$corpusDir);

        $storage = self::$kernel->getEntityTypeManager()->getStorage('example_sentence');

        // Consented + published: images are public.
        $consented = $storage->create([
            'ojibwe_text' => 'Shoogan Mookman',
            'english_text' => 'butter knife',
            'source_sentence_id' => 'corpus:sb-consent',
            'audio_url' => '/media/corpus/audio/sb-consent',
            'thumbnail_url' => '/media/corpus/thumb/sb-consent',
            'context_image_url' => '/media/corpus/context/sb-consent',
            'context_image_credit' => 'Wikipedia / Wikimedia Commons — Butter knife',
            'context_image_source' => 'https://upload.wikimedia.org/sb-consent.jpg',
            'context_image_article' => 'https://en.wikipedia.org/wiki/Butter_knife',
            'consent_public' => 1,
            'consent_ai_training' => 1,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $storage->save($consented);

        // Not consented + unpublished: must never be served, file or not.
        $private = $storage->create([
            'ojibwe_text' => 'private',
            'english_text' => 'private',
            'source_sentence_id' => 'corpus:sb-private',
            'thumbnail_url' => '/media/corpus/thumb/sb-private',
            'consent_public' => 0,
            'consent_ai_training' => 0,
            'status' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $storage->save($private);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$previousCorpusPath === null) {
            putenv('MINOO_CORPUS_PATH');
        } else {
            putenv('MINOO_CORPUS_PATH=' . self::$previousCorpusPath);
        }

        foreach (['thumbs/sb-consent.jpg', 'context-images/sb-consent.jpg', 'thumbs/sb-private.jpg'] as $rel) {
            @unlink(self::$corpusDir . '/' . $rel);
        }
        @rmdir(self::$corpusDir . '/thumbs');
        @rmdir(self::$corpusDir . '/context-images');
        @rmdir(self::$corpusDir);

        parent::tearDownAfterClass();
    }

    #[Test]
    public function consented_thumbnail_is_served_as_jpeg(): void
    {
        $response = $this->send('GET', '/media/corpus/thumb/sb-consent');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('image/jpeg', $response->headers->get('Content-Type'));
        self::assertSame(self::THUMB_BYTES, (string) $response->getContent());
    }

    #[Test]
    public function consented_context_image_is_served_as_jpeg(): void
    {
        $response = $this->send('GET', '/media/corpus/context/sb-consent');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('image/jpeg', $response->headers->get('Content-Type'));
        self::assertSame(self::CONTEXT_BYTES, (string) $response->getContent());
    }

    #[Test]
    public function non_consented_thumbnail_is_denied_even_though_the_file_exists(): void
    {
        $response = $this->send('GET', '/media/corpus/thumb/sb-private');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringNotContainsString('SHOULD-NOT-BE-SERVED', (string) $response->getContent());
    }

    #[Test]
    public function unknown_id_is_404(): void
    {
        self::assertSame(Response::HTTP_NOT_FOUND, $this->send('GET', '/media/corpus/thumb/sb-nope')->getStatusCode());
        self::assertSame(Response::HTTP_NOT_FOUND, $this->send('GET', '/media/corpus/context/sb-nope')->getStatusCode());
    }

    #[Test]
    public function media_fields_round_trip_on_the_entity(): void
    {
        $storage = self::$kernel->getEntityTypeManager()->getStorage('example_sentence');
        $ids = $storage->getQuery()->accessCheck(false)
            ->condition('source_sentence_id', 'corpus:sb-consent')
            ->execute();
        self::assertNotSame([], $ids);

        $entity = $storage->load(reset($ids));
        self::assertNotNull($entity);
        self::assertSame('/media/corpus/thumb/sb-consent', (string) $entity->get('thumbnail_url'));
        self::assertSame('/media/corpus/context/sb-consent', (string) $entity->get('context_image_url'));
        self::assertStringContainsString('Wikimedia', (string) $entity->get('context_image_credit'));
        self::assertStringContainsString('wikipedia.org', (string) $entity->get('context_image_article'));
    }
}
