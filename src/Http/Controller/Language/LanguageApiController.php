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
     * GET /api/lang/dialects: the language and dialect groupings the API exposes,
     * as BCP 47 tags. Community tags (oj-x-<community>) are open provenance and
     * are not enumerated here.
     */
    public function dialects(): JsonResponse
    {
        return $this->json([
            'language' => ['tag' => 'oj', 'label' => $this->dialects->label('oj')],
            'dialects' => $this->dialects->all(),
        ]);
    }

    /**
     * GET /api/lang/translate?q=&tag=: look up one English string. `tag` is a BCP
     * 47 tag (oj, an oj-<dialect> tag, or oj-x-<community>); `dialect` is accepted
     * as a back-compat alias. A malformed tag is a 422.
     *
     * @param array<string, mixed> $query
     */
    public function translate(#[MapQuery] array $query, AccountInterface $account): JsonResponse
    {
        $q = is_string($query['q'] ?? null) ? trim($query['q']) : '';
        if ($q === '') {
            return $this->json(['error' => 'Missing required query parameter: q'], 422);
        }

        $raw = $query['tag'] ?? $query['dialect'] ?? null;
        $tag = is_string($raw) && $raw !== '' ? $raw : null;
        if ($tag !== null && !$this->dialects->isValid($tag)) {
            return $this->json([
                'error' => 'Malformed language tag',
                'tag' => $tag,
                'hint' => 'Expected a BCP 47 tag: oj, an oj-<dialect> tag, or oj-x-<community> (for example oj-x-sagamok).',
                'recognized_dialects' => $this->dialects->codes(),
            ], 422);
        }

        return $this->json($this->translationMemory->lookup($q, $tag, $account));
    }
}
