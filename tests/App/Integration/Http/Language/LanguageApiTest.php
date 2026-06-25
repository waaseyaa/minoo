<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Language;

use App\Language\TranslationMemoryService;
use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public /api/lang surface (issue #894): exact, fuzzy, and logged-miss
 * lookups, consent gating, dialect validation, and the dialects listing. The
 * language module is enabled in config/anokii.yaml, so the routes are mounted.
 */
#[CoversNothing]
final class LanguageApiTest extends HttpKernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $storage = self::$kernel->getEntityTypeManager()->getStorage('translation_memory');
        $seed = static function (array $values) use ($storage): void {
            $source = (string) $values['source_en'];
            $storage->save($storage->create($values + [
                'source_hash' => TranslationMemoryService::hash(TranslationMemoryService::normalize($source)),
                'consent_public' => 1,
                'status' => 1,
                'created_at' => time(),
                'updated_at' => time(),
            ]));
        };

        $seed(['source_en' => 'bear', 'translation' => 'makwa', 'dialect_code' => 'oji-east', 'confidence' => 90, 'needs_speaker_review' => 0]);
        $seed(['source_en' => 'good morning', 'translation' => 'mino-gigizheb', 'dialect_code' => 'oji-east', 'confidence' => 60, 'needs_speaker_review' => 1]);
        // Consent-gated row: must never surface to anonymous callers.
        $storage->save($storage->create([
            'source_en' => 'secret word',
            'source_hash' => TranslationMemoryService::hash(TranslationMemoryService::normalize('secret word')),
            'translation' => 'giimooj',
            'dialect_code' => 'oji-east',
            'consent_public' => 0,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]));
    }

    #[Test]
    public function dialects_endpoint_lists_the_codes(): void
    {
        $response = $this->send('GET', '/api/lang/dialects');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $body = $this->decode($response);
        $codes = array_column($body['dialects'], 'code');
        self::assertContains('oji-east', $codes);
        self::assertContains('oji-ottawa', $codes);
    }

    #[Test]
    public function exact_match_is_case_and_whitespace_insensitive(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/translate', ['q' => '  Bear ', 'dialect' => 'oji-east']));

        self::assertSame('exact', $body['match_type']);
        self::assertSame('makwa', $body['translation']);
        self::assertSame(90, $body['confidence']);
        self::assertFalse($body['needs_speaker_review']);
    }

    #[Test]
    public function a_close_string_fuzzy_matches_and_carries_a_score(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/translate', ['q' => 'good mornin', 'dialect' => 'oji-east']));

        self::assertSame('fuzzy', $body['match_type']);
        self::assertSame('mino-gigizheb', $body['translation']);
        self::assertTrue($body['needs_speaker_review']);
        self::assertArrayHasKey('match_score', $body);
        self::assertGreaterThanOrEqual(TranslationMemoryService::FUZZY_THRESHOLD, $body['match_score']);
    }

    #[Test]
    public function a_miss_is_reported_and_logged_as_a_gap(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/translate', ['q' => 'quantum entanglement', 'dialect' => 'oji-east']));
        self::assertSame('miss', $body['match_type']);

        $gaps = self::$kernel->getEntityTypeManager()->getStorage('tm_gap_log');
        $ids = $gaps->getQuery()->accessCheck(false)
            ->condition('source_hash', TranslationMemoryService::hash('quantum entanglement'))
            ->execute();
        self::assertNotEmpty($ids, 'The miss wrote a gap-log row.');
    }

    #[Test]
    public function repeated_misses_increment_the_gap_request_count(): void
    {
        $this->send('GET', '/api/lang/translate', ['q' => 'helicopter', 'dialect' => 'oji-east']);
        $this->send('GET', '/api/lang/translate', ['q' => 'helicopter', 'dialect' => 'oji-east']);

        $gaps = self::$kernel->getEntityTypeManager()->getStorage('tm_gap_log');
        $ids = $gaps->getQuery()->accessCheck(false)
            ->condition('source_hash', TranslationMemoryService::hash('helicopter'))
            ->condition('dialect_code', 'oji-east')
            ->execute();
        self::assertCount(1, $ids, 'Repeat misses dedupe to one row.');
        $gap = $gaps->load(reset($ids));
        self::assertNotNull($gap);
        self::assertGreaterThanOrEqual(2, (int) $gap->get('request_count'));
    }

    #[Test]
    public function consent_gated_rows_never_surface(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/translate', ['q' => 'secret word', 'dialect' => 'oji-east']));

        self::assertSame('miss', $body['match_type'], 'A consent_public=0 row must not be returned.');
    }

    #[Test]
    public function missing_query_is_a_422(): void
    {
        $response = $this->send('GET', '/api/lang/translate', ['dialect' => 'oji-east']);
        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function unknown_dialect_is_a_422(): void
    {
        $response = $this->send('GET', '/api/lang/translate', ['q' => 'bear', 'dialect' => 'klingon']);
        self::assertSame(422, $response->getStatusCode());
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
