<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Language;

use App\Language\TranslationMemoryService;
use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public /api/lang surface on the BCP 47 tag contract (issues #894, #898):
 * exact tag match, fallback to oj, dialect-derived grouping, fuzzy, logged miss,
 * consent gating, tag validation, and the tag-aware dialects listing. The
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

        // Sagamok community rows.
        $seed(['source_en' => 'bear', 'translation' => 'makwa', 'language_tag' => 'oj-x-sagamok', 'confidence' => 90, 'needs_speaker_review' => 0]);
        $seed(['source_en' => 'good morning', 'translation' => 'mino-gigizheb', 'language_tag' => 'oj-x-sagamok', 'confidence' => 60, 'needs_speaker_review' => 1]);
        // Tag-agnostic row (bare oj) for the fallback-to-oj case.
        $seed(['source_en' => 'water', 'translation' => 'nibi', 'language_tag' => 'oj']);
        // Same dialect grouping (serpent-river is also oji-east) for the
        // dialect-derived fallback: no Sagamok "fox" row exists.
        $seed(['source_en' => 'fox', 'translation' => 'waagosh', 'language_tag' => 'oj-x-serpent-river']);
        // Consent-gated Sagamok row: must never surface to anonymous callers.
        $storage->save($storage->create([
            'source_en' => 'secret word',
            'source_hash' => TranslationMemoryService::hash(TranslationMemoryService::normalize('secret word')),
            'translation' => 'giimooj',
            'language_tag' => 'oj-x-sagamok',
            'consent_public' => 0,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]));
    }

    #[Test]
    public function dialects_endpoint_lists_tags(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/dialects'));

        self::assertSame('oj', $body['language']['tag']);
        $byCode = [];
        foreach ($body['dialects'] as $row) {
            $byCode[$row['code']] = $row;
        }
        self::assertSame('oj-ojg', $byCode['oji-east']['tag']);
        self::assertArrayHasKey('oji-ottawa', $byCode);
    }

    #[Test]
    public function exact_community_tag_match_is_case_insensitive_and_carries_the_tag(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/translate', ['q' => '  Bear ', 'tag' => 'oj-x-sagamok']));

        self::assertSame('exact', $body['match_type']);
        self::assertSame('makwa', $body['translation']);
        self::assertSame('oj-x-sagamok', $body['tag']);
        self::assertSame('Nishnaabemwin (Sagamok)', $body['label']);
        self::assertSame(90, $body['confidence']);
        self::assertFalse($body['needs_speaker_review']);
    }

    #[Test]
    public function it_falls_back_to_the_tag_agnostic_oj_row(): void
    {
        // "water" exists only as the bare-oj row; a Sagamok query still finds it.
        $body = $this->decode($this->send('GET', '/api/lang/translate', ['q' => 'water', 'tag' => 'oj-x-sagamok']));

        self::assertSame('exact', $body['match_type']);
        self::assertSame('nibi', $body['translation']);
        self::assertSame('oj', $body['tag']);
    }

    #[Test]
    public function it_falls_back_to_the_same_dialect_grouping(): void
    {
        // "fox" exists only as serpent-river; both serpent-river and sagamok are
        // oji-east, so a Sagamok query resolves it via the derived grouping.
        $body = $this->decode($this->send('GET', '/api/lang/translate', ['q' => 'fox', 'tag' => 'oj-x-sagamok']));

        self::assertSame('exact', $body['match_type']);
        self::assertSame('waagosh', $body['translation']);
        self::assertSame('oj-x-serpent-river', $body['tag']);
    }

    #[Test]
    public function a_close_string_fuzzy_matches_within_the_tag(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/translate', ['q' => 'good mornin', 'tag' => 'oj-x-sagamok']));

        self::assertSame('fuzzy', $body['match_type']);
        self::assertSame('mino-gigizheb', $body['translation']);
        self::assertGreaterThanOrEqual(TranslationMemoryService::FUZZY_THRESHOLD, $body['match_score']);
    }

    #[Test]
    public function a_miss_is_logged_as_a_gap_keyed_on_the_full_tag(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/translate', ['q' => 'quantum entanglement', 'tag' => 'oj-x-sagamok']));
        self::assertSame('miss', $body['match_type']);

        $gaps = self::$kernel->getEntityTypeManager()->getStorage('tm_gap_log');
        $ids = $gaps->getQuery()->accessCheck(false)
            ->condition('source_hash', TranslationMemoryService::hash('quantum entanglement'))
            ->condition('language_tag', 'oj-x-sagamok')
            ->execute();
        self::assertNotEmpty($ids, 'The miss wrote a gap-log row keyed on the full community tag.');
    }

    #[Test]
    public function consent_gated_rows_never_surface(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/translate', ['q' => 'secret word', 'tag' => 'oj-x-sagamok']));

        self::assertSame('miss', $body['match_type']);
    }

    #[Test]
    public function missing_query_is_a_422(): void
    {
        self::assertSame(422, $this->send('GET', '/api/lang/translate', ['tag' => 'oj-x-sagamok'])->getStatusCode());
    }

    #[Test]
    public function a_malformed_tag_is_a_422(): void
    {
        // The old custom dialect code is no longer a valid tag.
        self::assertSame(422, $this->send('GET', '/api/lang/translate', ['q' => 'bear', 'tag' => 'oji-east'])->getStatusCode());
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
