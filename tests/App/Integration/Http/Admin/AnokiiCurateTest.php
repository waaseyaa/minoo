<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Ingestion\Curation\UtterancePromoter;
use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Curation (#855): UtterancePromoter materializes dictionary_entry + word_part
 * from an utterance (verbatim, consent copied, idempotent), and the Anokii
 * curate tab + promote API are role-gated.
 */
#[CoversNothing]
final class AnokiiCurateTest extends HttpKernelTestCase
{
    private const string CSRF = 'curate-test-csrf-token';
    private static int $adminUid = 0;
    private static int $phraseEsid = 0;
    private static int $wordEsid = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $etm = self::$kernel->getEntityTypeManager();

        $admin = $etm->getStorage('user')->create(['name' => 'Curate Admin', 'mail' => 'curate-admin@example.test', 'status' => true, 'created' => time(), 'roles' => ['admin'], 'permissions' => []]);
        $etm->getStorage('user')->save($admin);
        self::$adminUid = (int) $admin->id();

        $sentences = $etm->getStorage('example_sentence');

        // Multi-word phrase, published + consented.
        $phrase = $sentences->create([
            'ojibwe_text' => 'Shoogan Mookman',
            'english_text' => 'butter knife',
            'source_sentence_id' => 'corpus:sb-c1',
            'source_url' => 'https://fb/reel/c1',
            'consent_public' => 1,
            'consent_ai_training' => 0,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $sentences->save($phrase);
        self::$phraseEsid = (int) $phrase->id();

        // Single word.
        $single = $sentences->create([
            'ojibwe_text' => 'Zhooniyaans',
            'english_text' => 'coin',
            'source_sentence_id' => 'corpus:sb-c2',
            'consent_public' => 1,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $sentences->save($single);
        self::$wordEsid = (int) $single->id();
    }

    private function promoter(): UtterancePromoter
    {
        return new UtterancePromoter(self::$kernel->getEntityTypeManager());
    }

    #[Test]
    public function promote_creates_dictionary_entry_and_word_parts_verbatim(): void
    {
        $result = $this->promoter()->promote(self::$phraseEsid);

        self::assertNotNull($result);
        self::assertTrue($result->created);
        self::assertCount(2, $result->wordPartIds, 'Two-word phrase yields two word_parts.');

        $etm = self::$kernel->getEntityTypeManager();
        $entry = $etm->getStorage('dictionary_entry')->load($result->dictionaryEntryId);
        self::assertNotNull($entry);
        self::assertSame('Shoogan Mookman', (string) $entry->get('word'), 'Ojibwe stored verbatim.');
        // JSON-wrapped like every other dictionary_entry, so the public Dictionary
        // browse (definition LIKE '%"%') includes it and the detail view decodes it (#910).
        self::assertSame('["butter knife"]', (string) $entry->get('definition'));
        self::assertStringContainsString('"', (string) $entry->get('definition'), 'Passes the Dictionary browse filter.');
        // Consent + publication copied from the source sentence.
        self::assertSame(1, (int) $entry->get('consent_public'));
        self::assertSame(1, (int) $entry->get('status'));

        // word_part forms are the two tokens.
        $forms = [];
        foreach ($result->wordPartIds as $wpid) {
            $forms[] = (string) $etm->getStorage('word_part')->load($wpid)->get('form');
        }
        sort($forms);
        self::assertSame(['Mookman', 'Shoogan'], $forms);

        // Source sentence linked back.
        $sentence = $etm->getStorage('example_sentence')->load(self::$phraseEsid);
        self::assertSame($result->dictionaryEntryId, (int) $sentence->get('dictionary_entry_id'));
    }

    #[Test]
    public function promote_is_idempotent(): void
    {
        $first = $this->promoter()->promote(self::$wordEsid);
        $second = $this->promoter()->promote(self::$wordEsid);

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertTrue($first->created);
        self::assertFalse($second->created, 'Re-promoting returns the existing entry.');
        self::assertSame($first->dictionaryEntryId, $second->dictionaryEntryId);
        self::assertSame([], $first->wordPartIds, 'Single word yields no word_parts.');
    }

    #[Test]
    public function curate_dashboard_is_gated(): void
    {
        $admin = $this->sendAs(self::$adminUid, 'GET', '/admin/anokii/curate');
        self::assertSame(Response::HTTP_OK, $admin->getStatusCode());
        self::assertStringContainsString('cu-dash', (string) $admin->getContent());
        self::assertStringNotContainsString('/_nuxt/', (string) $admin->getContent());

        $anon = $this->send('GET', '/admin/anokii/curate');
        self::assertContains($anon->getStatusCode(), [Response::HTTP_FORBIDDEN, Response::HTTP_FOUND]);
        self::assertStringNotContainsString('cu-dash', (string) $anon->getContent());
    }

    #[Test]
    public function promote_route_creates_entry_and_rejects_unauthorized(): void
    {
        // Fresh utterance so this test owns the promotion.
        $sentences = self::$kernel->getEntityTypeManager()->getStorage('example_sentence');
        $row = $sentences->create([
            'ojibwe_text' => 'Nibi',
            'english_text' => 'water',
            'source_sentence_id' => 'corpus:sb-c3',
            'consent_public' => 1,
            'status' => 1,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $sentences->save($row);
        $esid = (int) $row->id();

        // Unauthorized first: must not create anything.
        $denied = $this->sendPost(0, '/admin/anokii/curate/promote', ['esid' => (string) $esid]);
        self::assertContains($denied->getStatusCode(), [Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED]);
        self::assertSame(0, (int) $sentences->load($esid)->get('dictionary_entry_id'), 'Denied promote must not mutate.');

        // Authorized: creates + links.
        $ok = $this->sendPost(self::$adminUid, '/admin/anokii/curate/promote', ['esid' => (string) $esid]);
        self::assertSame(Response::HTTP_OK, $ok->getStatusCode());
        $payload = json_decode((string) $ok->getContent(), true);
        self::assertTrue($payload['ok'] ?? false);
        self::assertGreaterThan(0, (int) ($payload['dictionary_entry_id'] ?? 0));
        self::assertSame((int) $payload['dictionary_entry_id'], (int) $sentences->load($esid)->get('dictionary_entry_id'));
    }

    #[Test]
    public function publish_auto_promotes_and_makes_a_public_dictionary_entry(): void
    {
        $etm = self::$kernel->getEntityTypeManager();
        $sentences = $etm->getStorage('example_sentence');
        // Transcribed but NOT promoted (deid 0), not yet public.
        $row = $sentences->create([
            'ojibwe_text' => 'Emkwaan',
            'english_text' => 'spoon',
            'source_sentence_id' => 'corpus:sb-c4',
            'consent_public' => 0,
            'status' => 0,
            'pipeline_status' => 'transcribed',
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $sentences->save($row);
        $esid = (int) $row->id();

        $ok = $this->sendPost(self::$adminUid, '/admin/anokii/curate/publish', ['esid' => (string) $esid]);
        self::assertSame(Response::HTTP_OK, $ok->getStatusCode());
        $payload = json_decode((string) $ok->getContent(), true);
        self::assertTrue($payload['ok'] ?? false);
        $deid = (int) ($payload['dictionary_entry_id'] ?? 0);
        self::assertGreaterThan(0, $deid, 'Publish auto-promotes so a dictionary_entry exists.');

        // The sentence is published and linked.
        $sentence = $sentences->load($esid);
        self::assertSame('published', (string) $sentence->get('pipeline_status'));
        self::assertSame($deid, (int) $sentence->get('dictionary_entry_id'));

        // The dictionary_entry is exactly what the public Dictionary browse selects:
        // status 1, consent_public 1, JSON-wrapped definition (contains a quote).
        $entries = $etm->getStorage('dictionary_entry');
        $entry = $entries->load($deid);
        self::assertSame(1, (int) $entry->get('status'));
        self::assertSame(1, (int) $entry->get('consent_public'));
        self::assertSame('["spoon"]', (string) $entry->get('definition'));

        $browse = $entries->getQuery()->accessCheck(false)
            ->condition('status', 1)->condition('consent_public', 1)
            ->condition('definition', '%"%', 'LIKE')
            ->condition('slug', (string) $entry->get('slug'))
            ->execute();
        self::assertContains($deid, array_map('intval', $browse), 'Published word appears in the public Dictionary browse.');
    }

    #[Test]
    public function publish_refuses_a_word_with_no_anishinaabemowin(): void
    {
        $sentences = self::$kernel->getEntityTypeManager()->getStorage('example_sentence');
        $row = $sentences->create([
            'ojibwe_text' => '',
            'english_text' => 'open',
            'source_sentence_id' => 'corpus:sb-c5',
            'status' => 0,
            'pipeline_status' => 'drafted',
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $sentences->save($row);
        $esid = (int) $row->id();

        $res = $this->sendPost(self::$adminUid, '/admin/anokii/curate/publish', ['esid' => (string) $esid]);
        self::assertSame(422, $res->getStatusCode(), 'Cannot publish a word with no Anishinaabemowin into the void.');
        self::assertStringContainsString('Anishinaabemowin', (string) $res->getContent());
        // Unchanged: not published, no entry.
        $after = $sentences->load($esid);
        self::assertNotSame('published', (string) $after->get('pipeline_status'));
        self::assertSame(0, (int) $after->get('dictionary_entry_id'));
    }

    private function sendAs(int $uid, string $method, string $uri): Response
    {
        $this->primeGlobals($method, $uri);
        $this->primeSession($uid);

        return self::$kernel->handle();
    }

    /**
     * @param array<string, string> $fields
     */
    private function sendPost(int $uid, string $uri, array $fields): Response
    {
        $this->primeGlobals('POST', $uri);
        $_POST = $fields + ['_csrf_token' => self::CSRF];
        $_REQUEST = $_POST;
        $this->primeSession($uid);

        return self::$kernel->handle();
    }

    private function primeGlobals(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_REQUEST = [];
        $_FILES = [];
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path,
            'QUERY_STRING' => '',
            'PATH_INFO' => $path,
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTPS' => '',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
        ]);
    }

    private function primeSession(int $uid): void
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['_csrf_token'] = self::CSRF;
        if ($uid > 0) {
            $_SESSION['waaseyaa_uid'] = $uid;
        } else {
            unset($_SESSION['waaseyaa_uid']);
        }
    }
}
