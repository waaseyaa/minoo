<?php

declare(strict_types=1);

namespace App\Http\Controller\Language;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * Public corpus images (#852) — parallels {@see CorpusAudioController}.
 *
 * Streams a single example-sentence image from the community-controlled corpus
 * directory (MINOO_CORPUS_PATH), which is NEVER committed to the repo:
 *   - thumb: the whiteboard keyframe (thumbs/<id>.jpg), one per item.
 *
 * Consent boundary: an image is served ONLY when its example_sentence row is
 * consent_public = 1 AND status = 1 — the same gate that governs the text and
 * the audio. Path traversal is impossible: the id is regex-validated to the
 * corpus naming scheme before any filesystem access, and only the exact
 * <id>.jpg under the fixed sub-directory is read.
 */
final class CorpusImageController
{
    private const string DEFAULT_CORPUS_PATH = 'C:/Users/jones/Projects/LLC/anishinaabemowin/content/corpus';

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {
    }

    /** Whiteboard keyframe. @param array<string, mixed> $params */
    public function thumb(#[MapRoute] array $params, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->serve((string) ($params['id'] ?? ''), 'thumbs', $account);
    }

    private function serve(string $id, string $subdir, AccountInterface $account): Response
    {
        // Strict allowlist on shape: corpus ids are lowercase letters, digits,
        // and hyphens only. No '.', '/', or '\' can reach the path.
        if ($id === '' || preg_match('/^[a-z0-9-]+$/', $id) !== 1) {
            return new Response('Not found', 404);
        }

        if (!$this->isConsentedSentence($id, $account)) {
            return new Response('Not found', 404);
        }

        $file = $this->corpusPath() . '/' . $subdir . '/' . $id . '.jpg';
        if (!is_file($file)) {
            return new Response('Not found', 404);
        }

        return new Response((string) file_get_contents($file), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * True only when a published, consent-public example_sentence carries this
     * corpus id. The query is the access boundary for the image.
     */
    private function isConsentedSentence(string $id, AccountInterface $account): bool
    {
        if (!$this->entityTypeManager->hasDefinition('example_sentence')) {
            return false;
        }

        $ids = $this->entityTypeManager->getStorage('example_sentence')->getQuery()
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
