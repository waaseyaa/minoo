<?php

declare(strict_types=1);

namespace App\Http\Controller\Anokii;

use App\Anokii\Pipeline\PipelineCounts;
use App\Anokii\Pipeline\PipelineStage;
use App\Anokii\Pipeline\PipelineStageResolver;
use App\Http\View\AnokiiShellContext;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
 * Ingest tab (#877) — drag-and-drop multi-reel ingestion, the headline of
 * Anokii Workspace v2.
 *
 * The drop zone uploads each video to the persistent MINOO_CORPUS_PATH staging
 * area and immediately creates a draft example_sentence (pipeline_status=
 * ingested) linked to the video, then RETURNS — the heavy work (ffmpeg keyframe
 * + opus, vision draft) is done out-of-band by the `anokii:process-reels`
 * worker over the {@see ReelProcessor} seam. The UI polls {@see self::status()}
 * per row and a finished reel (drafted) flows straight into Transcribe.
 *
 * The FB-URL ingest path is kept via {@see self::url()} (and the existing
 * `ingest:corpus` CLI). Both routes are role-gated (admin / elder_coordinator)
 * by {@see \App\Provider\Routing\AnokiiRouteProvider}. Ojibwe is never
 * normalized (ADR 0003); Steven's corpus is fully consented.
 */
final class IngestController
{
    private const string DEFAULT_CORPUS_PATH = 'C:/Users/jones/Projects/LLC/anishinaabemowin/content/corpus';

    // Mirror config/waaseyaa.php corpus_upload_* — the PHP/Caddy web-tier limits
    // must also be raised in the deployment image for ~100 MB videos.
    private const int MAX_BYTES = 120 * 1024 * 1024;

    /** @var list<string> */
    private const array ALLOWED_MIME = ['video/mp4', 'video/quicktime', 'video/webm'];

    /**
     * Validation is by client extension/mime, not server finfo: the fileinfo
     * extension is not guaranteed in every runtime, and these uploads come from
     * staff (role-gated route). The real content check is downstream — ffmpeg in
     * ReelProcessor rejects anything that is not decodable video.
     *
     * @var list<string>
     */
    private const array ALLOWED_EXT = ['mp4', 'mov', 'm4v', 'webm'];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {
    }

    public function index(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('pages/anokii/ingest.html.twig', AnokiiShellContext::build($account, 'ingest', [
            'path' => $request->getPathInfo(),
            'rows' => $this->activeRows(),
            'upload_url' => '/admin/anokii/ingest/upload',
            'status_url' => '/admin/anokii/ingest/status',
            'url_url' => '/admin/anokii/ingest/url',
            'transcribe_url' => '/admin/anokii/transcribe',
            'max_mb' => intdiv(self::MAX_BYTES, 1024 * 1024),
            'accept' => implode(',', self::ALLOWED_MIME),
            'csrf_token' => CsrfMiddleware::token(),
            'pipeline' => (new PipelineCounts($this->entityTypeManager))->compute(),
            'pipeline_active' => 'ingested',
        ]));

        return new Response($html);
    }

    /**
     * Accept one or many uploaded videos. Each is staged to
     * MINOO_CORPUS_PATH/source-videos/<id>.mp4 and gets a draft example_sentence
     * (pipeline_status=ingested). Processing is deferred to the worker. Returns
     * a per-file result list so the drop zone can render each row.
     */
    public function upload(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $files = $this->uploadedFiles($request);
        if ($files === []) {
            return $this->json(['ok' => false, 'error' => 'No files received.'], 422);
        }

        $results = [];
        foreach ($files as $file) {
            $results[] = $this->stageOne($file);
        }

        return $this->json(['ok' => true, 'files' => $results]);
    }

    /**
     * Poll processing status for a set of rows. `esids` is a comma-separated list.
     */
    public function status(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $raw = (string) $request->query->get('esids', '');
        $esids = array_values(array_filter(array_map('intval', explode(',', $raw)), static fn (int $n): bool => $n > 0));
        if ($esids === []) {
            return $this->json(['ok' => true, 'rows' => []]);
        }

        $storage = $this->entityTypeManager->getStorage('example_sentence');
        $rows = [];
        foreach ($esids as $esid) {
            $row = $storage->load($esid);
            if (!$row instanceof EntityInterface) {
                continue;
            }
            $rows[] = [
                'esid' => $esid,
                'pipeline_status' => (string) ($row->get('pipeline_status') ?: PipelineStage::INGESTED),
                'ojibwe_text' => (string) $row->get('ojibwe_text'),
                'english_text' => (string) $row->get('english_text'),
                'error' => $this->processingError($row),
            ];
        }

        return $this->json(['ok' => true, 'rows' => $rows]);
    }

    /**
     * Keep the Facebook-URL ingest path: register a draft row for a reel URL. The
     * media is produced when its source video is staged and the worker (or
     * `ingest:corpus`) runs — the same operator prerequisite as the CLI path.
     */
    public function url(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $data = $this->readBody($request);
        $url = trim((string) ($data['url'] ?? ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return $this->json(['ok' => false, 'error' => 'A valid http(s) URL is required.'], 422);
        }

        $id = 'fb-' . bin2hex(random_bytes(4));
        $esid = $this->createDraft($id, $url, '');

        return $this->json(['ok' => true, 'esid' => $esid, 'corpus_id' => $id, 'pipeline_status' => PipelineStage::INGESTED]);
    }

    /**
     * @return array{corpus_id: string, esid: int|null, status: string, error: string|null, filename: string}
     */
    private function stageOne(UploadedFile $file): array
    {
        $name = $file->getClientOriginalName();

        if (!$file->isValid()) {
            return ['corpus_id' => '', 'esid' => null, 'status' => 'error', 'error' => 'Upload failed (' . $file->getErrorMessage() . ').', 'filename' => $name];
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return ['corpus_id' => '', 'esid' => null, 'status' => 'error', 'error' => 'Too large (max ' . intdiv(self::MAX_BYTES, 1024 * 1024) . ' MB).', 'filename' => $name];
        }
        $ext = strtolower($file->getClientOriginalExtension());
        $clientMime = (string) $file->getClientMimeType();
        if (!in_array($ext, self::ALLOWED_EXT, true) && !in_array($clientMime, self::ALLOWED_MIME, true)) {
            return ['corpus_id' => '', 'esid' => null, 'status' => 'error', 'error' => 'Unsupported type. Use mp4, mov, or webm.', 'filename' => $name];
        }

        $id = 'upload-' . bin2hex(random_bytes(5));
        $dir = $this->corpusPath() . '/source-videos';
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            return ['corpus_id' => '', 'esid' => null, 'status' => 'error', 'error' => 'Could not create staging directory.', 'filename' => $name];
        }

        try {
            $file->move($dir, $id . '.mp4');
        } catch (\Throwable $e) {
            return ['corpus_id' => '', 'esid' => null, 'status' => 'error', 'error' => 'Could not stage file: ' . $e->getMessage(), 'filename' => $name];
        }

        $esid = $this->createDraft($id, '', $name);

        return ['corpus_id' => $id, 'esid' => $esid, 'status' => PipelineStage::INGESTED, 'error' => null, 'filename' => $name];
    }

    /**
     * Create the ingested draft example_sentence for a staged reel. Off all
     * public surfaces (consent off, status 0) until reviewed and published.
     */
    private function createDraft(string $id, string $sourceUrl, string $filename): int
    {
        $storage = $this->entityTypeManager->getStorage('example_sentence');
        $now = time();
        $entity = $storage->create([
            'ojibwe_text' => '',
            'english_text' => '',
            'notes' => '',
            'source_sentence_id' => 'corpus:' . $id,
            'source_url' => $sourceUrl,
            'video_url' => '/admin/anokii/media/video/' . $id,
            'language_code' => 'oj',
            'pipeline_status' => PipelineStage::INGESTED,
            'provenance' => (string) json_encode(['id' => $id, 'source_url' => $sourceUrl, 'original_filename' => $filename], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'consent_public' => 0,
            'consent_ai_training' => 0,
            'status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $storage->save($entity);

        return (int) $entity->id();
    }

    /**
     * Active ingestion rows (ingested or drafted), newest first, for the tab list.
     *
     * @return list<array{esid: int, corpus_id: string, pipeline_status: string, filename: string, error: string|null}>
     */
    private function activeRows(): array
    {
        if (!$this->entityTypeManager->hasDefinition('example_sentence')) {
            return [];
        }

        $storage = $this->entityTypeManager->getStorage('example_sentence');
        // accessCheck(false): staff-gated tool; must show unreviewed drafts. The
        // route's requireRole is the access boundary. See the bypass audit doc.
        $ids = $storage->getQuery()
            ->accessCheck(false)
            ->condition('source_sentence_id', 'corpus:%', 'LIKE')
            ->sort('created_at', 'DESC')
            ->execute();

        if ($ids === []) {
            return [];
        }

        $resolver = new PipelineStageResolver();
        $rows = [];
        foreach ($storage->loadMultiple($ids) as $row) {
            $stage = $resolver->resolve($row);
            if ($stage !== PipelineStage::INGESTED && $stage !== PipelineStage::DRAFTED) {
                continue;
            }
            $corpusId = str_replace('corpus:', '', (string) $row->get('source_sentence_id'));
            $rows[] = [
                'esid' => (int) $row->id(),
                'corpus_id' => $corpusId,
                'pipeline_status' => $stage,
                'filename' => $this->originalFilename($row, $corpusId),
                'error' => $this->processingError($row),
            ];
            if (count($rows) >= 50) {
                break;
            }
        }

        return $rows;
    }

    private function originalFilename(EntityInterface $row, string $fallback): string
    {
        $prov = json_decode((string) ($row->get('provenance') ?? ''), true);
        $name = is_array($prov) ? trim((string) ($prov['original_filename'] ?? '')) : '';

        return $name !== '' ? $name : $fallback;
    }

    private function processingError(EntityInterface $row): ?string
    {
        $prov = json_decode((string) ($row->get('provenance') ?? ''), true);
        if (!is_array($prov)) {
            return null;
        }
        $error = trim((string) ($prov['processing_error'] ?? ''));

        return $error !== '' ? $error : null;
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedFiles(HttpRequest $request): array
    {
        $flat = [];
        $all = $request->files->all();
        array_walk_recursive(
            $all,
            static function ($file) use (&$flat): void {
                if ($file instanceof UploadedFile) {
                    $flat[] = $file;
                }
            },
        );

        return $flat;
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

    private function corpusPath(): string
    {
        $env = getenv('MINOO_CORPUS_PATH');

        return is_string($env) && $env !== '' ? rtrim($env, '/\\') : self::DEFAULT_CORPUS_PATH;
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
