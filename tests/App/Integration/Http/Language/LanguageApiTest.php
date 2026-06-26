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
        // Same dialect grouping (serpent-river is also nishnaabemwin) for the
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

        // dictionary_entry rows for the /api/lang/lookup lexicon endpoint. Own
        // corpus (attribution_source 'corpus') vs OPD (must NEVER be served).
        $entries = self::$kernel->getEntityTypeManager()->getStorage('dictionary_entry');
        $entry = static function (array $values) use ($entries): void {
            $entries->save($entries->create($values + [
                'language_code' => 'oj',
                'status' => 1,
                'consent_public' => 1,
                'created_at' => time(),
                'updated_at' => time(),
            ]));
        };
        // Own community corpus.
        $entry(['word' => 'Emkwaan', 'slug' => 'emkwaan', 'definition' => '["spoon"]', 'attribution_source' => 'corpus', 'attribution_url' => 'https://www.facebook.com/reel/901425976280455']);
        $entry(['word' => 'naagnens', 'slug' => 'naagnens', 'definition' => '["cup, glass"]', 'attribution_source' => 'corpus', 'attribution_url' => 'https://www.facebook.com/reel/1454763089179769']);
        // OPD row: licensed external reference, must never appear in /api/lang.
        $entry(['word' => 'aw', 'slug' => 'aw', 'definition' => '["that (animate)"]', 'language_code' => 'oj-sw', 'license' => 'CC BY-NC-SA 3.0', 'attribution_source' => "Ojibwe People's Dictionary, University of Minnesota", 'attribution_url' => 'https://ojibwe.lib.umn.edu/main-entry/aw-pron']);
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
        self::assertSame('oj-ojg', $byCode['nishnaabemwin']['tag']);
        self::assertArrayNotHasKey('oji-east', $byCode);
        self::assertArrayNotHasKey('oji-ottawa', $byCode);
    }

    #[Test]
    public function dialects_endpoint_carries_the_opd_usage_notice(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/dialects'));

        self::assertTrue($body['usage']['noncommercial']);
        self::assertSame('CC BY-NC-SA 3.0', $body['usage']['license']);
        self::assertSame('https://ojibwe.lib.umn.edu', $body['usage']['source_url']);
    }

    #[Test]
    public function translate_response_carries_the_opd_usage_notice(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/translate', ['q' => 'water', 'tag' => 'oj']));

        self::assertSame('CC BY-NC-SA 3.0', $body['usage']['license']);
        self::assertTrue($body['usage']['noncommercial']);
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
        // nishnaabemwin, so a Sagamok query resolves it via the derived grouping.
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

    #[Test]
    public function lookup_returns_a_corpus_word_for_an_english_term(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/lookup', ['q' => 'spoon']));

        self::assertSame('exact', $body['match_type']);
        self::assertSame(1, $body['count']);
        $match = $body['matches'][0];
        self::assertSame('Emkwaan', $match['word']);
        self::assertSame(['spoon'], $match['definition']);
        self::assertSame('oj-x-sagamok', $match['tag']);
        self::assertSame('Nishnaabemwin', $match['dialect']);
        self::assertSame('en', $match['matched_on']);
        self::assertSame('corpus', $match['provenance']['attribution_source']);
    }

    #[Test]
    public function lookup_matches_an_anishinaabemowin_term_in_the_oj_direction(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/lookup', ['q' => 'Emkwaan', 'dir' => 'oj']));

        self::assertSame('exact', $body['match_type']);
        self::assertSame('Emkwaan', $body['matches'][0]['word']);
        self::assertSame('oj', $body['matches'][0]['matched_on']);
    }

    #[Test]
    public function lookup_splits_comma_senses_so_cup_matches(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/lookup', ['q' => 'cup']));

        self::assertSame('exact', $body['match_type']);
        self::assertSame('naagnens', $body['matches'][0]['word']);
    }

    #[Test]
    public function lookup_fuzzy_matches_a_near_spelling(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/lookup', ['q' => 'spoonn']));

        self::assertSame('fuzzy', $body['match_type']);
        self::assertSame('Emkwaan', $body['matches'][0]['word']);
        self::assertGreaterThanOrEqual(70, $body['matches'][0]['match_score']);
    }

    #[Test]
    public function lookup_never_returns_opd_content(): void
    {
        // 'aw' is a seeded OPD row. It must not surface in either direction, and
        // no match in any lookup may carry an umn.edu (OPD) source.
        $body = $this->decode($this->send('GET', '/api/lang/lookup', ['q' => 'aw']));
        self::assertSame('miss', $body['match_type']);
        self::assertSame([], $body['matches']);

        $byOj = $this->decode($this->send('GET', '/api/lang/lookup', ['q' => 'aw', 'dir' => 'oj']));
        self::assertSame('miss', $byOj['match_type']);

        // Sweep every corpus match across a broad query; none may be OPD.
        $all = $this->decode($this->send('GET', '/api/lang/lookup', ['q' => 'a']));
        foreach ($all['matches'] as $match) {
            self::assertStringNotContainsStringIgnoringCase('ojibwe.lib.umn.edu', (string) ($match['provenance']['source_url'] ?? ''));
            self::assertSame('corpus', $match['provenance']['attribution_source']);
        }
    }

    #[Test]
    public function lookup_with_a_valid_but_other_dialect_tag_returns_no_matches(): void
    {
        // oj-ojb (Northwestern Ojibwe) is well-formed but selects no community we
        // hold, so it is a 200 miss, not a 422.
        $body = $this->decode($this->send('GET', '/api/lang/lookup', ['q' => 'spoon', 'tag' => 'oj-ojb']));

        self::assertSame('miss', $body['match_type']);
        self::assertSame([], $body['matches']);
    }

    #[Test]
    public function lookup_accepts_the_community_and_agnostic_tags(): void
    {
        foreach (['oj-x-sagamok', 'oj'] as $tag) {
            $body = $this->decode($this->send('GET', '/api/lang/lookup', ['q' => 'spoon', 'tag' => $tag]));
            self::assertSame('exact', $body['match_type'], "tag {$tag} should resolve corpus content");
            self::assertSame('Emkwaan', $body['matches'][0]['word']);
        }
    }

    #[Test]
    public function lookup_malformed_tag_is_a_422(): void
    {
        self::assertSame(422, $this->send('GET', '/api/lang/lookup', ['q' => 'spoon', 'tag' => 'oji-east'])->getStatusCode());
    }

    #[Test]
    public function lookup_invalid_dir_is_a_422(): void
    {
        self::assertSame(422, $this->send('GET', '/api/lang/lookup', ['q' => 'spoon', 'dir' => 'sideways'])->getStatusCode());
    }

    #[Test]
    public function lookup_missing_q_is_a_422(): void
    {
        self::assertSame(422, $this->send('GET', '/api/lang/lookup', [])->getStatusCode());
    }

    #[Test]
    public function lookup_usage_notice_is_community_governed_not_opd(): void
    {
        $body = $this->decode($this->send('GET', '/api/lang/lookup', ['q' => 'spoon']));

        self::assertSame('OCAP', $body['usage']['governance']);
        self::assertTrue($body['usage']['community_governed']);
        // No invented licence for the corpus: it is the community's to set.
        self::assertNull($body['usage']['license']);
        // OPD is acknowledged as a reference only, never served.
        self::assertSame('https://ojibwe.lib.umn.edu', $body['usage']['reference']['url']);
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
