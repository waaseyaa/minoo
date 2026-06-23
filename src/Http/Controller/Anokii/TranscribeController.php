<?php

declare(strict_types=1);

namespace App\Http\Controller\Anokii;

use App\Http\View\AnokiiShellContext;
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
 * Transcribe dashboard (#853) — replaces the standalone Python prototype
 * (code/transcribe/transcribe.py, in a separate repo) with an SSR tab inside
 * the Anokii shell.
 *
 * Lists corpus example_sentence rows (default-filtered to untranscribed: those
 * missing the Ojibwe or English text), each with the whiteboard thumbnail and
 * audio (#852/#822 consent-gated routes) plus editable Ojibwe / English / notes.
 * Edits autosave to the entity via {@see self::save()}.
 *
 * Both routes are role-gated (admin / elder_coordinator) by
 * {@see \App\Provider\Routing\AnokiiRouteProvider}. The page renders into the
 * Anokii package shell via the `@anokii` namespace; it never forks it.
 *
 * HARD REQUIREMENT: the Ojibwe is stored EXACTLY as entered — never normalize
 * the speaker's orthography (ADR 0003).
 */
final class TranscribeController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {
    }

    public function index(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $showAll = ($request->query->get('filter') === 'all');

        $rows = $this->corpusRows();
        $total = count($rows);
        $done = count(array_filter($rows, static fn (array $r): bool => $r['ojibwe_text'] !== '' && $r['english_text'] !== ''));

        $visible = $showAll
            ? $rows
            : array_values(array_filter($rows, static fn (array $r): bool => $r['ojibwe_text'] === '' || $r['english_text'] === ''));

        $html = $this->twig->render('pages/anokii/transcribe.html.twig', AnokiiShellContext::build($account, 'transcribe', [
            'path' => $request->getPathInfo(),
            'rows' => $visible,
            'total' => $total,
            'done' => $done,
            'show_all' => $showAll,
            'save_url' => '/admin/anokii/transcribe/save',
            'csrf_token' => CsrfMiddleware::token(),
        ]));

        return new Response($html);
    }

    /**
     * Autosave a single row. Accepts a JSON body (the dashboard's fetch) or form
     * fields (test/no-JS fallback). CSRF is enforced by the framework middleware
     * (X-CSRF-Token header or _csrf_token field); the route enforces the role.
     */
    public function save(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data = $this->readBody($request);

        $esid = (int) ($data['esid'] ?? 0);
        if ($esid <= 0) {
            return $this->json(['ok' => false, 'error' => 'Missing esid.'], 422);
        }

        $storage = $this->entityTypeManager->getStorage('example_sentence');
        $entity = $storage->load($esid);
        if (!$entity instanceof EntityInterface) {
            return $this->json(['ok' => false, 'error' => 'Not found.'], 404);
        }

        // Stored verbatim — the Ojibwe orthography is never normalized (ADR 0003).
        if (array_key_exists('ojibwe_text', $data)) {
            $entity->set('ojibwe_text', (string) $data['ojibwe_text']);
        }
        if (array_key_exists('english_text', $data)) {
            $entity->set('english_text', (string) $data['english_text']);
        }
        if (array_key_exists('notes', $data)) {
            $entity->set('notes', (string) $data['notes']);
        }
        $entity->set('updated_at', time());
        $storage->save($entity);

        return $this->json([
            'ok' => true,
            'esid' => $esid,
            'transcribed' => (string) $entity->get('ojibwe_text') !== '' && (string) $entity->get('english_text') !== '',
        ]);
    }

    /**
     * Corpus rows as flat arrays for the template. Admin-gated route, so the
     * account is bound for the access check; all imported corpus rows are
     * consent-public, so they all surface.
     *
     * @return list<array{esid: int, ojibwe_text: string, english_text: string, notes: string, thumb_url: string, audio_url: string, source_url: string, corpus_id: string}>
     */
    private function corpusRows(): array
    {
        if (!$this->entityTypeManager->hasDefinition('example_sentence')) {
            return [];
        }

        $storage = $this->entityTypeManager->getStorage('example_sentence');
        // accessCheck(false): this is the transcribe tool, gated to staff by the
        // route. It must surface UNREVIEWED drafts (status 0 / consent off) from
        // ingest:corpus (#854) — which an access-checked query would hide. The
        // route's requireRole is the access boundary. See the bypass audit doc.
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
            $corpusId = str_replace('corpus:', '', (string) $entity->get('source_sentence_id'));
            $rows[] = [
                'esid' => (int) $entity->id(),
                'ojibwe_text' => (string) $entity->get('ojibwe_text'),
                'english_text' => (string) $entity->get('english_text'),
                'notes' => (string) ($entity->get('notes') ?? ''),
                'thumb_url' => (string) ($entity->get('thumbnail_url') ?: ($corpusId !== '' ? '/media/corpus/thumb/' . $corpusId : '')),
                'audio_url' => (string) ($entity->get('audio_url') ?: ($corpusId !== '' ? '/media/corpus/audio/' . $corpusId : '')),
                'source_url' => (string) $entity->get('source_url'),
                'corpus_id' => $corpusId,
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
