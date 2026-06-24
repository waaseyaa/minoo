<?php

declare(strict_types=1);

namespace App\Http\Controller\Anokii;

use App\Anokii\Pipeline\PipelineCounts;
use App\Http\View\AnokiiShellContext;
use App\Ingestion\Curation\UtterancePromoter;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
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
        $rows = $this->rows();
        $promoted = count(array_filter($rows, static fn (array $r): bool => $r['promoted']));

        $html = $this->twig->render('pages/anokii/curate.html.twig', AnokiiShellContext::build($account, 'curate', [
            'path' => $request->getPathInfo(),
            'rows' => $rows,
            'total' => count($rows),
            'promoted' => $promoted,
            'promote_url' => '/admin/anokii/curate/promote',
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
        ]);
    }

    /**
     * @return list<array{esid: int, ojibwe_text: string, english_text: string, promoted: bool, dictionary_entry_id: int, parts: int}>
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
