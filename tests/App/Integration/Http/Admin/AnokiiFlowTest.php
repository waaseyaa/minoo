<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Anokii\Pipeline\PipelineStage;
use App\Http\Controller\Anokii\CurateController;
use App\Http\Controller\Anokii\TranscribeController;
use App\Tests\Integration\Http\HttpKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Anokii flow wiring (#878): the transitions that connect the stages —
 * Transcribe "mark transcribed" (drafted -> transcribed), Curate publish
 * (-> published, public) and add-to-lesson. Steven's corpus is fully consented,
 * so publishing turns consent on. Ojibwe stays verbatim (ADR 0003).
 */
#[CoversNothing]
final class AnokiiFlowTest extends HttpKernelTestCase
{
    private static int $adminUid = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $users = self::$kernel->getEntityTypeManager()->getRepository('user');
        $admin = $users->create(['name' => 'Flow Admin', 'mail' => 'flow-admin@example.test', 'status' => true, 'created' => time(), 'roles' => ['admin'], 'permissions' => []]);
        $users->save($admin);
        self::$adminUid = (int) $admin->id();
    }

    private function account(): AccountInterface
    {
        return self::$kernel->getEntityTypeManager()->getRepository('user')->find((string) self::$adminUid);
    }

    private function transcribe(): TranscribeController
    {
        return new TranscribeController(self::$kernel->getEntityTypeManager(), SsrServiceProvider::getTwigEnvironment());
    }

    private function curate(): CurateController
    {
        return new CurateController(self::$kernel->getEntityTypeManager(), SsrServiceProvider::getTwigEnvironment());
    }

    private function jsonRequest(array $body): HttpRequest
    {
        $request = new HttpRequest();
        $request->initialize([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($body));

        return $request;
    }

    private function seed(array $values): int
    {
        $storage = self::$kernel->getEntityTypeManager()->getRepository('example_sentence');
        $row = $storage->create($values + ['consent_public' => 0, 'status' => 0, 'created_at' => time(), 'updated_at' => time()]);
        $storage->save($row);

        return (int) $row->id();
    }

    #[Test]
    public function mark_transcribed_advances_a_complete_draft(): void
    {
        $esid = $this->seed(['ojibwe_text' => '  Makwa  ', 'english_text' => 'bear', 'source_sentence_id' => 'corpus:fl-1', 'pipeline_status' => PipelineStage::DRAFTED]);

        $payload = json_decode((string) $this->transcribe()->save([], [], $this->account(), $this->jsonRequest(['esid' => $esid, 'mark' => 'transcribed']))->getContent(), true);

        self::assertTrue($payload['ok']);
        self::assertSame(PipelineStage::TRANSCRIBED, $payload['pipeline_status']);

        $row = self::$kernel->getEntityTypeManager()->getRepository('example_sentence')->find((string) $esid);
        self::assertSame(PipelineStage::TRANSCRIBED, (string) $row->get('pipeline_status'));
        self::assertSame('  Makwa  ', (string) $row->get('ojibwe_text'), 'Ojibwe stays verbatim.');
    }

    #[Test]
    public function mark_transcribed_is_refused_when_incomplete(): void
    {
        $esid = $this->seed(['ojibwe_text' => 'Makwa', 'english_text' => '', 'source_sentence_id' => 'corpus:fl-2', 'pipeline_status' => PipelineStage::DRAFTED]);

        $response = $this->transcribe()->save([], [], $this->account(), $this->jsonRequest(['esid' => $esid, 'mark' => 'transcribed']));
        self::assertSame(422, $response->getStatusCode());

        $row = self::$kernel->getEntityTypeManager()->getRepository('example_sentence')->find((string) $esid);
        self::assertSame(PipelineStage::DRAFTED, (string) $row->get('pipeline_status'), 'Incomplete row must not advance.');
    }

    #[Test]
    public function publish_makes_the_utterance_public_and_published(): void
    {
        $esid = $this->seed(['ojibwe_text' => 'Nibi', 'english_text' => 'water', 'source_sentence_id' => 'corpus:fl-3', 'pipeline_status' => PipelineStage::TRANSCRIBED]);

        $payload = json_decode((string) $this->curate()->publish([], [], $this->account(), $this->jsonRequest(['esid' => $esid]))->getContent(), true);
        self::assertTrue($payload['ok']);
        self::assertSame(PipelineStage::PUBLISHED, $payload['pipeline_status']);

        $row = self::$kernel->getEntityTypeManager()->getRepository('example_sentence')->find((string) $esid);
        self::assertSame(1, (int) $row->get('status'));
        self::assertSame(1, (int) $row->get('consent_public'));
        self::assertSame(PipelineStage::PUBLISHED, (string) $row->get('pipeline_status'));
    }

    #[Test]
    public function add_to_lesson_records_a_known_slug_and_rejects_unknown(): void
    {
        $esid = $this->seed(['ojibwe_text' => 'Makademashkikiwaaboo', 'english_text' => 'coffee', 'source_sentence_id' => 'corpus:fl-4', 'pipeline_status' => PipelineStage::TRANSCRIBED]);

        $ok = json_decode((string) $this->curate()->lesson([], [], $this->account(), $this->jsonRequest(['esid' => $esid, 'lesson_slug' => 'the-kitchen']))->getContent(), true);
        self::assertTrue($ok['ok']);

        $row = self::$kernel->getEntityTypeManager()->getRepository('example_sentence')->find((string) $esid);
        self::assertSame('the-kitchen', (string) $row->get('lesson_slug'));

        $bad = $this->curate()->lesson([], [], $this->account(), $this->jsonRequest(['esid' => $esid, 'lesson_slug' => 'nope']));
        self::assertSame(422, $bad->getStatusCode());
    }
}
