<?php

declare(strict_types=1);

namespace App\Http\Controller\Language;

use App\Http\Controller\Concerns\JsonResponseTrait;
use App\Language\DialectCodeProvider;
use App\Language\TranslationMemoryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\Attribute\MapQuery;

/**
 * The public /api/lang JSON surface (issue #894), mounted as a peer to the
 * package /api/chat and gated on the language module flag by
 * {@see \App\Provider\Routing\LanguageApiRouteProvider}.
 *
 * Read-only English-to-Anishinaabemowin lookups against the translation memory.
 * Consent gating is enforced at the entity layer (the request account is passed
 * through to the access-checked query), so anonymous callers only ever see
 * published, public-consent rows.
 */
final class LanguageApiController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly DialectCodeProvider $dialects,
        private readonly TranslationMemoryService $translationMemory,
    ) {
    }

    /**
     * GET /api/lang/dialects: the dialect codes the API accepts.
     */
    public function dialects(): JsonResponse
    {
        return $this->json(['dialects' => $this->dialects->all()]);
    }

    /**
     * GET /api/lang/translate?q=&dialect=: look up one English string.
     *
     * @param array<string, mixed> $query
     */
    public function translate(#[MapQuery] array $query, AccountInterface $account): JsonResponse
    {
        $q = is_string($query['q'] ?? null) ? trim($query['q']) : '';
        if ($q === '') {
            return $this->json(['error' => 'Missing required query parameter: q'], 422);
        }

        $dialect = is_string($query['dialect'] ?? null) && $query['dialect'] !== '' ? $query['dialect'] : null;
        if ($dialect !== null && !$this->dialects->isValid($dialect)) {
            return $this->json([
                'error' => 'Unknown dialect code',
                'dialect' => $dialect,
                'valid' => $this->dialects->codes(),
            ], 422);
        }

        return $this->json($this->translationMemory->lookup($q, $dialect, $account));
    }
}
