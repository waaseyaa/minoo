<?php

declare(strict_types=1);

namespace App\Http\Controller\Language;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * Public corpus audio (#822 / Phase 4).
 *
 * Streams a single example-sentence audio clip from the community-controlled
 * corpus directory (MINOO_CORPUS_PATH), which is NEVER committed to the repo.
 *
 * Consent boundary: a clip is served ONLY when its example_sentence row is
 * consent_public = 1 AND status = 1. The consent gate that governs the text in
 * search and the cite-only chat governs the audio too. The audio itself is the
 * speaker's own public Facebook reel (recorded in source_url), so a consented
 * row's clip is public. Path traversal is impossible: the id is regex-validated
 * to the corpus naming scheme before any filesystem access, and only the exact
 * <id>.opus under audio/ is read.
 */
final class CorpusAudioController
{
    private const string DEFAULT_CORPUS_PATH = 'C:/Users/jones/Projects/LLC/anishinaabemowin/content/corpus';

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {
    }

    /** @param array<string, mixed> $params */
    public function audio(#[MapRoute] array $params, AccountInterface $account, HttpRequest $request): Response
    {
        $id = (string) ($params['id'] ?? '');

        // Strict allowlist on shape: corpus ids are lowercase letters, digits,
        // and hyphens only. No '.', '/', or '\' can reach the path.
        if ($id === '' || preg_match('/^[a-z0-9-]+$/', $id) !== 1) {
            return new Response('Not found', 404);
        }

        if (!$this->isConsentedSentence($id, $account)) {
            return new Response('Not found', 404);
        }

        $file = $this->corpusPath() . '/audio/' . $id . '.opus';
        if (!is_file($file)) {
            return new Response('Not found', 404);
        }

        return new Response((string) file_get_contents($file), 200, [
            'Content-Type' => 'audio/ogg',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * True only when a published, consent-public example_sentence carries this
     * corpus id. The query is the access boundary for the audio.
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
