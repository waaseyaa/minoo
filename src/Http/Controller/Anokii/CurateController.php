<?php

declare(strict_types=1);

namespace App\Http\Controller\Anokii;

use App\Anokii\Pipeline\PipelineCounts;
use App\Anokii\Pipeline\PipelineStage;
use App\Anokii\Pipeline\PipelineStageResolver;
use App\Http\View\AnokiiShellContext;
use App\Ingestion\Curation\UtterancePromoter;
use App\Lesson\LessonCatalog;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;
use Waaseyaa\User\Middleware\CsrfMiddleware;

/**
 * Curation tab (#855) — promotes corpus utterances into structured vocabulary
 * (dictionary_entry + word_part) inside the Anokii shell.
 *
 * Lists corpus example_sentence rows with a "Promote" action; promoting creates
 * a dictionary_entry (and word_parts for multi-word phrases) via
 * {@see UtterancePromoter} and links the sentence back. Both routes are
 * role-gated (admin / elder_coordinator) by {@see \App\Provider\Routing\AnokiiRouteProvider}.
 * Ojibwe is carried verbatim — never normalized (ADR 0003).
 */
final class CurateController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {
    }

    public function index(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $stage = (string) $request->query->get('stage', '');
        $all = $this->rows();

        // Default view: items ready to curate (transcribed). ?stage filters to a
        // single stage (the Overview funnel + breadcrumb link here).
        if (PipelineStage::isValid($stage)) {
            $rows = array_values(array_filter($all, static fn (array $r): bool => $r['pipeline_status'] === $stage));
        } else {
            $rows = array_values(array_filter($all, static fn (array $r): bool => $r['pipeline_status'] === PipelineStage::TRANSCRIBED));
        }

        $promoted = count(array_filter($all, static fn (array $r): bool => $r['promoted']));

        $html = $this->twig->render('pages/anokii/curate.html.twig', AnokiiShellContext::build($account, 'curate', [
            'path' => $request->getPathInfo(),
            'rows' => $rows,
            'shown' => count($rows),
            'total' => count($all),
            'promoted' => $promoted,
            'stage' => PipelineStage::isValid($stage) ? $stage : PipelineStage::TRANSCRIBED,
            'lessons' => LessonCatalog::options(),
            'promote_url' => '/admin/anokii/curate/promote',
            'publish_url' => '/admin/anokii/curate/publish',
            'lesson_url' => '/admin/anokii/curate/lesson',
            'transcribe_url' => '/admin/anokii/transcribe',
            'lessons_url' => '/lessons',
            'overview_url' => '/admin/anokii',
            'csrf_token' => CsrfMiddleware::token(),
            'pipeline' => (new PipelineCounts($this->entityTypeManager))->compute(),
            'pipeline_active' => 'transcribed',
        ]));

        return new Response($html);
    }

    public function promote(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data = $this->readBody($request);
        $esid = (int) ($data['esid'] ?? 0);
        if ($esid <= 0) {
            return $this->json(['ok' => false, 'error' => 'Missing esid.'], 422);
        }

        $result = (new UtterancePromoter($this->entityTypeManager))->promote($esid);
        if ($result === null) {
            return $this->json(['ok' => false, 'error' => 'Not found or empty.'], 404);
        }

        return $this->json([
            'ok' => true,
            'esid' => $esid,
            'dictionary_entry_id' => $result->dictionaryEntryId,
            'word_parts' => $result->wordPartIds,
            'created' => $result->created,
            'pipeline_status' => PipelineStage::CURATED,
        ]);
    }

    /**
     * Publish a curated utterance: flip it (and its dictionary_entry, if any) to
     * public + published. Steven's corpus is fully consented, so publishing sets
     * consent_public and consent_ai_training on.
     */
    public function publish(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data = $this->readBody($request);
        $esid = (int) ($data['esid'] ?? 0);
        if ($esid <= 0) {
            return $this->json(['ok' => false, 'error' => 'Missing esid.'], 422);
        }

        $sentences = $this->entityTypeManager->getStorage('example_sentence');
        $sentence = $sentences->load($esid);
        if (!$sentence instanceof EntityInterface) {
            return $this->json(['ok' => false, 'error' => 'Not found.'], 404);
        }

        // A published word must have a public dictionary_entry or it surfaces
        // nowhere (#910). Auto-promote when it was not curated first; a row with
        // no Anishinaabemowin word cannot become a dictionary entry, so refuse
        // rather than publish into the void.
        $deid = (int) ($sentence->get('dictionary_entry_id') ?? 0);
        if ($deid === 0) {
            if (trim((string) $sentence->get('ojibwe_text')) === '') {
                return $this->json(['ok' => false, 'error' => 'Add the Anishinaabemowin word before publishing.'], 422);
            }
            $promotion = (new UtterancePromoter($this->entityTypeManager))->promote($esid);
            if ($promotion === null) {
                return $this->json(['ok' => false, 'error' => 'Could not promote this utterance to a dictionary entry.'], 422);
            }
            $deid = $promotion->dictionaryEntryId;
            // promote() mutated and saved the row; reload before publishing.
            $sentence = $sentences->load($esid);
            if (!$sentence instanceof EntityInterface) {
                return $this->json(['ok' => false, 'error' => 'Not found.'], 404);
            }
        }

        $now = time();
        $this->publishEntity($sentence);
        $sentence->set('pipeline_status', PipelineStage::PUBLISHED);
        $sentence->set('updated_at', $now);
        $sentences->save($sentence);

        // Publish the dictionary_entry too, so the word goes live in the Dictionary.
        $entries = $this->entityTypeManager->getStorage('dictionary_entry');
        $entry = $entries->load($deid);
        if ($entry instanceof EntityInterface) {
            $this->publishEntity($entry);
            $entry->set('updated_at', $now);
            $entries->save($entry);
        }

        return $this->json(['ok' => true, 'esid' => $esid, 'dictionary_entry_id' => $deid, 'pipeline_status' => PipelineStage::PUBLISHED]);
    }

    /**
     * Associate a curated utterance with a lesson (#878). Lessons are static
     * config keyed by slug; this records the chosen slug on the row.
     */
    public function lesson(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data = $this->readBody($request);
        $esid = (int) ($data['esid'] ?? 0);
        $slug = trim((string) ($data['lesson_slug'] ?? ''));
        if ($esid <= 0) {
            return $this->json(['ok' => false, 'error' => 'Missing esid.'], 422);
        }
        if (!in_array($slug, LessonCatalog::slugs(), true)) {
            return $this->json(['ok' => false, 'error' => 'Unknown lesson.'], 422);
        }

        $sentences = $this->entityTypeManager->getStorage('example_sentence');
        $sentence = $sentences->load($esid);
        if (!$sentence instanceof EntityInterface) {
            return $this->json(['ok' => false, 'error' => 'Not found.'], 404);
        }

        $sentence->set('lesson_slug', $slug);
        $sentence->set('updated_at', time());
        $sentences->save($sentence);

        return $this->json(['ok' => true, 'esid' => $esid, 'lesson_slug' => $slug]);
    }

    private function publishEntity(EntityInterface $entity): void
    {
        $entity->set('status', 1);
        $entity->set('consent_public', 1);
        $entity->set('consent_ai_training', 1);
    }

    /**
     * @return list<array{esid: int, ojibwe_text: string, english_text: string, promoted: bool, dictionary_entry_id: int, parts: int, pipeline_status: string, lesson_slug: string, published: bool}>
     */
    private function rows(): array
    {
        if (!$this->entityTypeManager->hasDefinition('example_sentence')) {
            return [];
        }

        $storage = $this->entityTypeManager->getStorage('example_sentence');
        // accessCheck(false): staff-gated curation tool; must show drafts too.
        // The route's requireRole is the access boundary. See the bypass audit doc.
        $ids = $storage->getQuery()
            ->accessCheck(false)
            ->condition('source_sentence_id', 'corpus:%', 'LIKE')
            ->sort('source_sentence_id')
            ->execute();

        if ($ids === []) {
            return [];
        }

        $resolver = new PipelineStageResolver();
        $rows = [];
        foreach ($storage->loadMultiple($ids) as $entity) {
            $word = (string) $entity->get('ojibwe_text');
            $deid = (int) ($entity->get('dictionary_entry_id') ?? 0);
            $rows[] = [
                'esid' => (int) $entity->id(),
                'ojibwe_text' => $word,
                'english_text' => (string) $entity->get('english_text'),
                'promoted' => $deid > 0,
                'dictionary_entry_id' => $deid,
                'parts' => count(preg_split('/\s+/', trim($word), -1, PREG_SPLIT_NO_EMPTY) ?: []),
                'pipeline_status' => $resolver->resolve($entity),
                'lesson_slug' => (string) ($entity->get('lesson_slug') ?? ''),
                'published' => (int) ($entity->get('status') ?? 0) === 1,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function readBody(HttpRequest $request): array
    {
        $raw = trim((string) $request->getContent());
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->request->all();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload, int $status = 200): Response
    {
        return new Response(
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json'],
        );
    }
}
