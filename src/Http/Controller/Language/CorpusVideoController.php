<?php

declare(strict_types=1);

namespace App\Http\Controller\Language;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * Public corpus video (#873) — the web-optimized teaching reel for a lesson card.
 *
 * Streams the H.264/AAC mp4 from the community-controlled corpus directory
 * (MINOO_CORPUS_PATH/video/<id>.mp4), which is NEVER committed to the repo, with
 * HTTP Range support (Accept-Ranges + 206 partial content) so users can scrub.
 *
 * Consent boundary: identical to the audio/image gate — a clip is served ONLY
 * when its example_sentence row is consent_public = 1 AND status = 1. Steven
 * Bennett's corpus is fully consented (display/publication/search/AI), so every
 * lesson card's reel is public. Path traversal is impossible: the id is
 * regex-validated to the corpus naming scheme before any filesystem access.
 */
final class CorpusVideoController
{
    private const string DEFAULT_CORPUS_PATH = 'C:/Users/jones/Projects/LLC/anishinaabemowin/content/corpus';

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {
    }

    /** @param array<string, mixed> $params */
    public function video(#[MapRoute] array $params, AccountInterface $account, HttpRequest $request): Response
    {
        $id = (string) ($params['id'] ?? '');
        if ($id === '' || preg_match('/^[a-z0-9-]+$/', $id) !== 1) {
            return new Response('Not found', 404);
        }

        if (!$this->isConsentedSentence($id, $account)) {
            return new Response('Not found', 404);
        }

        $file = $this->corpusPath() . '/video/' . $id . '.mp4';
        if (!is_file($file)) {
            return new Response('Not found', 404);
        }

        $size = (int) filesize($file);
        $start = 0;
        $end = $size - 1;
        $status = 200;
        $headers = [
            'Content-Type' => 'video/mp4',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=3600',
        ];

        $range = $request->headers->get('Range');
        if (is_string($range) && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m) === 1) {
            if ($m[1] !== '') {
                $start = (int) $m[1];
            }
            if ($m[2] !== '') {
                $end = (int) $m[2];
            }
            if ($start > $end || $start >= $size) {
                // Unsatisfiable range.
                return new Response('', 416, ['Content-Range' => 'bytes */' . $size, 'Accept-Ranges' => 'bytes']);
            }
            $end = min($end, $size - 1);
            $status = 206;
            $headers['Content-Range'] = 'bytes ' . $start . '-' . $end . '/' . $size;
        }

        $length = $end - $start + 1;
        $headers['Content-Length'] = (string) $length;

        // Files are small (web-optimized, ~1-2 MB); read the requested slice.
        $body = ($start === 0 && $length === $size)
            ? (string) file_get_contents($file)
            : (string) file_get_contents($file, false, null, $start, $length);

        return new Response($body, $status, $headers);
    }

    /**
     * True only when a published, consent-public example_sentence carries this
     * corpus id. The query is the access boundary for the video.
     */
    private function isConsentedSentence(string $id, AccountInterface $account): bool
    {
        if (!$this->entityTypeManager->hasDefinition('example_sentence')) {
            return false;
        }

        $ids = $this->entityTypeManager->getRepository('example_sentence')->getQuery()
            ->setAccount($account)
            ->condition('status', 1)
            ->condition('consent_public', 1)
            ->condition('source_sentence_id', 'corpus:' . $id)
            ->range(0, 1)
            ->execute();

        return $ids !== [];
    }

    private function corpusPath(): string
    {
        $env = getenv('MINOO_CORPUS_PATH');

        return is_string($env) && $env !== '' ? rtrim($env, '/\\') : self::DEFAULT_CORPUS_PATH;
    }
}
