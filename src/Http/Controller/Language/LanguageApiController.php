<?php

declare(strict_types=1);

namespace App\Http\Controller\Language;

use App\Http\Controller\Concerns\JsonResponseTrait;
use App\Language\CorpusLexiconService;
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

    /**
     * Redistribution terms for the community corpus served by /api/lang/lookup.
     * Unlike the OPD (a licensed external reference), the Sagamok corpus is
     * community-governed under OCAP: there is no invented redistribution licence
     * here; formal terms are the community's to set. The OPD is acknowledged as a
     * separate reference only and is never served through this API.
     *
     * @var array<string, mixed>
     */
    private const CORPUS_USAGE_NOTICE = [
        'governance' => 'OCAP',
        'community_governed' => true,
        'noncommercial' => true,
        'attribution' => 'Sagamok community Knowledge Keepers',
        'license' => null,
        'terms' => 'Community-governed; redistribution terms are to be set by the community.',
        'notice' => 'Community-governed Anishinaabemowin (Nishnaabemwin) from the Sagamok community, shared under the community\'s own governance (OCAP: Ownership, Control, Access, Possession). This is NOT Ojibwe People\'s Dictionary content. Treat as non-commercial and consult the community before reuse; formal redistribution terms are to be set by the community.',
        'reference' => [
            'note' => 'The Ojibwe People\'s Dictionary (Southwestern Ojibwe, CC BY-NC-SA 3.0) is a separate reference and is never served through this API.',
            'url' => 'https://ojibwe.lib.umn.edu',
        ],
    ];

    public function __construct(
        private readonly DialectCodeProvider $dialects,
        private readonly TranslationMemoryService $translationMemory,
        private readonly CorpusLexiconService $corpus,
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

    /**
     * GET /api/lang/lookup?q=&tag=&dir=: look up one term in Minoo's OWN community
     * corpus (the Sagamok Nishnaabemwin lexicon). Reads only dictionary_entry rows
     * with attribution_source = 'corpus', which is also the OPD exclusion: no
     * Ojibwe People's Dictionary content is ever returned here.
     *
     * `dir` is 'en' (English term, match definitions), 'oj' (Anishinaabemowin
     * term, match the word), or omitted (match both). `tag` is a BCP 47 tag,
     * validated the same way as /api/lang/translate (a malformed tag is a 422).
     *
     * This is the lexicon endpoint; /api/lang/translate is the translation memory.
     * The endpoint is public but intended for server-to-server use (e.g.
     * rhtcircle's PHP backend); it sets no CORS header, so it is not callable from
     * a browser cross-origin.
     *
     * @param array<string, mixed> $query
     */
    public function lookup(#[MapQuery] array $query, AccountInterface $account): JsonResponse
    {
        $q = is_string($query['q'] ?? null) ? trim($query['q']) : '';
        if ($q === '') {
            return $this->json(['error' => 'Missing required query parameter: q'], 422);
        }

        $rawTag = $query['tag'] ?? null;
        $tag = is_string($rawTag) && $rawTag !== '' ? $rawTag : null;
        if ($tag !== null && !$this->dialects->isValid($tag)) {
            return $this->json([
                'error' => 'Malformed language tag',
                'tag' => $tag,
                'hint' => 'Expected a BCP 47 tag: oj, an oj-<dialect> tag, or oj-x-<community> (for example oj-x-sagamok).',
                'recognized_dialects' => $this->dialects->codes(),
            ], 422);
        }

        $rawDir = $query['dir'] ?? null;
        $dir = is_string($rawDir) && $rawDir !== '' ? strtolower($rawDir) : null;
        if ($dir !== null && !in_array($dir, ['en', 'oj'], true)) {
            return $this->json([
                'error' => 'Invalid dir parameter',
                'dir' => $rawDir,
                'hint' => "Expected 'en' (English term) or 'oj' (Anishinaabemowin term); omit to match both directions.",
            ], 422);
        }

        $result = $this->corpus->lookup($q, $tag, $dir, $account);
        $result['usage'] = self::CORPUS_USAGE_NOTICE;

        return $this->json($result);
    }
}
