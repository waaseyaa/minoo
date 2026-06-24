<?php

declare(strict_types=1);

namespace App\Http\Controller\Anokii;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * Staff-gated corpus media for the Anokii workspace (#877).
 *
 * The public corpus controllers (CorpusVideo/Image/AudioController) serve a clip
 * ONLY when its example_sentence row is status=1 AND consent_public=1. Ingest
 * drafts are status=0 by design, so staff could not see the reel they are about
 * to transcribe through those routes. This controller serves the same files from
 * MINOO_CORPUS_PATH WITHOUT the consent gate — the access boundary is instead the
 * route's requireRole(admin,elder_coordinator). It never touches public surfaces.
 *
 *   video -> source-videos/<id>.mp4   (the uploaded original, with Range support)
 *   thumb -> thumbs/<id>.jpg          (ffmpeg keyframe)
 *   audio -> audio/<id>.opus          (ffmpeg opus track)
 *
 * Path traversal is impossible: kind is whitelisted and id is regex-validated.
 */
final class AnokiiMediaController
{
    private const string DEFAULT_CORPUS_PATH = 'C:/Users/jones/Projects/LLC/anishinaabemowin/content/corpus';

    /** @var array<string, array{0: string, 1: string}> kind => [subdir/ext, content-type] */
    private const array KINDS = [
        'video' => ['source-videos|mp4', 'video/mp4'],
        'thumb' => ['thumbs|jpg', 'image/jpeg'],
        'audio' => ['audio|opus', 'audio/ogg'],
    ];

    /** @param array<string, mixed> $params */
    public function media(#[MapRoute] array $params, AccountInterface $account, HttpRequest $request): Response
    {
        $kind = (string) ($params['kind'] ?? '');
        $id = (string) ($params['id'] ?? '');

        if (!isset(self::KINDS[$kind]) || $id === '' || preg_match('/^[a-z0-9-]+$/', $id) !== 1) {
            return new Response('Not found', 404);
        }

        [$spec, $contentType] = self::KINDS[$kind];
        [$subdir, $ext] = explode('|', $spec);
        $file = $this->corpusPath() . '/' . $subdir . '/' . $id . '.' . $ext;
        if (!is_file($file)) {
            return new Response('Not found', 404);
        }

        $size = (int) filesize($file);
        $start = 0;
        $end = $size - 1;
        $status = 200;
        $headers = [
            'Content-Type' => $contentType,
            'Accept-Ranges' => 'bytes',
            // Drafts are unreviewed staff-only content — never cache.
            'Cache-Control' => 'private, no-store',
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
                return new Response('', 416, ['Content-Range' => 'bytes */' . $size, 'Accept-Ranges' => 'bytes']);
            }
            $end = min($end, $size - 1);
            $status = 206;
            $headers['Content-Range'] = 'bytes ' . $start . '-' . $end . '/' . $size;
        }

        $length = $end - $start + 1;
        $headers['Content-Length'] = (string) $length;

        $body = ($start === 0 && $length === $size)
            ? (string) file_get_contents($file)
            : (string) file_get_contents($file, false, null, $start, $length);

        return new Response($body, $status, $headers);
    }

    private function corpusPath(): string
    {
        $env = getenv('MINOO_CORPUS_PATH');

        return is_string($env) && $env !== '' ? rtrim($env, '/\\') : self::DEFAULT_CORPUS_PATH;
    }
}
