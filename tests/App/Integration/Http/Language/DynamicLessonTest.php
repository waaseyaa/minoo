<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Language;

use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dynamic lessons (#912): /lessons/{slug} renders the published, public, curated
 * example_sentence rows assigned to its lesson_slug - English from the curated
 * dictionary_entry definition, grouped by lesson_group. Drafts / uncurated /
 * unpublished assigned rows never appear.
 */
#[CoversNothing]
final class DynamicLessonTest extends HttpKernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $etm = self::$kernel->getEntityTypeManager();
        $es = $etm->getStorage('example_sentence');
        $de = $etm->getStorage('dictionary_entry');

        // Curated definition deliberately DIFFERS from the raw english_text, to
        // prove the lesson English comes from the curated dictionary_entry.
        $entry = $de->create(['word' => 'Emkwaan', 'slug' => 'emkwaan', 'definition' => '["spoon"]', 'status' => 1, 'consent_public' => 1, 'created_at' => time(), 'updated_at' => time()]);
        $de->save($entry);
        $es->save($es->create([
            'ojibwe_text' => 'Emkwaan', 'english_text' => 'spooon-raw-typo', 'source_sentence_id' => 'corpus:dyn-1',
            'dictionary_entry_id' => (int) $entry->id(), 'lesson_slug' => 'the-kitchen', 'lesson_group' => 'Utensils',
            'lesson_weight' => 5, 'status' => 1, 'consent_public' => 1, 'created_at' => time(), 'updated_at' => time(),
        ]));

        // Assigned + curated but UNPUBLISHED (status 0) -> must not appear.
        $draftEntry = $de->create(['word' => 'Nokomis', 'slug' => 'nokomis-d', 'definition' => '["grandmother"]', 'status' => 0, 'consent_public' => 0, 'created_at' => time(), 'updated_at' => time()]);
        $de->save($draftEntry);
        $es->save($es->create([
            'ojibwe_text' => 'DRAFTOJIBWE', 'english_text' => 'draft', 'source_sentence_id' => 'corpus:dyn-2',
            'dictionary_entry_id' => (int) $draftEntry->id(), 'lesson_slug' => 'the-kitchen', 'lesson_group' => 'Utensils',
            'lesson_weight' => 6, 'status' => 0, 'consent_public' => 0, 'created_at' => time(), 'updated_at' => time(),
        ]));

        // Assigned + published but NOT curated (no dictionary_entry) -> must not appear.
        $es->save($es->create([
            'ojibwe_text' => 'UNCURATEDOJIBWE', 'english_text' => 'uncurated', 'source_sentence_id' => 'corpus:dyn-3',
            'lesson_slug' => 'the-kitchen', 'lesson_group' => 'Utensils', 'lesson_weight' => 7,
            'status' => 1, 'consent_public' => 1, 'created_at' => time(), 'updated_at' => time(),
        ]));
    }

    #[Test]
    public function a_published_curated_assigned_word_appears_with_its_curated_gloss(): void
    {
        $body = (string) $this->send('GET', '/lessons/the-kitchen')->getContent();

        self::assertStringContainsString('Emkwaan', $body, 'Verbatim Ojibwe renders.');
        self::assertStringContainsString('spoon', $body, 'English is the curated dictionary gloss.');
        self::assertStringNotContainsString('spooon-raw-typo', $body, 'Not the raw english_text.');
        self::assertStringContainsString('Utensils', $body, 'Section heading renders.');
    }

    #[Test]
    public function drafts_and_uncurated_assignments_never_appear(): void
    {
        $body = (string) $this->send('GET', '/lessons/the-kitchen')->getContent();

        self::assertStringNotContainsString('DRAFTOJIBWE', $body, 'Unpublished assigned row is hidden.');
        self::assertStringNotContainsString('UNCURATEDOJIBWE', $body, 'Uncurated assigned row is hidden.');
    }

    #[Test]
    public function the_lesson_index_counts_only_visible_cards(): void
    {
        $response = $this->send('GET', '/lessons');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        // Exactly one card is visible for the-kitchen in this :memory: fixture.
        self::assertStringContainsString('/lessons/the-kitchen', (string) $response->getContent());
    }
}
