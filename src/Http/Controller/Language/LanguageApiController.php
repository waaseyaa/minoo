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

    /**
     * Redistribution terms carried in every /api/lang envelope. Southwestern
     * Ojibwe dictionary content originates from the Ojibwe People's Dictionary
     * and is licensed CC BY-NC-SA 3.0: downstream consumers must keep it
     * non-commercial, attribute the source, and share derivatives alike. The
     * Sagamok community corpus is not OPD content and carries its own terms.
     *
     * @var array<string, mixed>
     */
    private const USAGE_NOTICE = [
        'noncommercial' => true,
        'notice' => 'Southwestern Ojibwe dictionary content is copyrighted by The Ojibwe People\'s Dictionary and used under CC BY-NC-SA 3.0. Redistribution must remain non-commercial, credit the source, and carry this licence. Sagamok community corpus content is not OPD content and carries its own terms.',
        'attribution' => 'The Ojibwe People\'s Dictionary',
        'source_url' => 'https://ojibwe.lib.umn.edu',
        'license' => 'CC BY-NC-SA 3.0',
        'license_url' => 'https://creativecommons.org/licenses/by-nc-sa/3.0/',
    ];

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
            'usage' => self::USAGE_NOTICE,
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

        $result = $this->translationMemory->lookup($q, $tag, $account);
        if (!isset($result['usage'])) {
            $result['usage'] = self::USAGE_NOTICE;
        }

        return $this->json($result);
    }
}
