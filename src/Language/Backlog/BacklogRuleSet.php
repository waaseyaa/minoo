<?php

declare(strict_types=1);

namespace App\Language\Backlog;

/**
 * The inclusion + cleanup + clustering + categorisation rules for the translation
 * backlog (issue #906), encoding section 5 of the 2026-06-25 recon report. Pure
 * and side-effect-free so the policy is unit-tested in isolation; the generator
 * ({@see \App\Language\Backlog\BacklogBuilder}) applies it to the recon output.
 *
 * Everything here is rule-based only - no lemmatiser. Casefold is the dedupe key;
 * concept_key clusters surface variants while each surface form stays its own row.
 */
final class BacklogRuleSet
{
    /** Cross-site demand floor: below this is each nation's own program/place names. */
    public const int MIN_SITES = 2;

    /**
     * Theme chrome / generic link text / artifacts to HARD-DROP (report §5). Bare
     * month/date strings are dropped separately by {@see self::isDateNoise()}.
     *
     * @var list<string>
     */
    private const array HARD_DROP = [
        'skip to content', 'page load link', 'go to top', 'toggle navigation',
        'click here', 'read more', 'learn more', 'load more', 'search for', 'here',
        'close', 'next', 'previous', 'overview', 'list', 'recent posts',
        'email protected',
    ];

    /**
     * Generic application chrome: kept in the backlog (it still needs translating)
     * but tagged category=global-ui so its cross-site count is not read as
     * band-governance demand (report §5).
     *
     * @var list<string>
     */
    private const array GLOBAL_UI = [
        'search', 'login', 'log in', 'password', 'username', 'submit', 'send',
        'download', 'subscribe', 'email', 'phone', 'first name', 'last name',
        'remember me', 'forgot your password', 'members portal', 'members only',
    ];

    /**
     * Anishinaabemowin lexicon used by the classifier. A string is EXCLUDED from
     * the English backlog when every content token is in this set (i.e. the whole
     * string is already in the language) - greetings, program words, autonyms.
     * Mixed strings that merely embed an autonym ("Anishinabek Nation Governance
     * Agreement") are English proper nouns and are kept as category=other.
     *
     * @var array<string, true>
     */
    private const array OJIBWE_LEXICON = [
        'aanii' => true, 'aaniin' => true, 'boozhoo' => true, 'biindigen' => true,
        'niigaaniin' => true, 'anishinaabemowin' => true, 'nbisiing' => true,
        'mino' => true, 'bimaadiziwin' => true, 'maadiziwin' => true, 'giizhigad' => true,
        'anishinaabe' => true, 'anishinaabeg' => true, 'anishnawbek' => true,
        'miigwech' => true, 'miigwetch' => true, 'gichi' => true, 'gchi' => true,
        'manidoo' => true, 'nokomis' => true, 'mishomis' => true, 'ahaaw' => true,
        'baamaapii' => true, 'miikaan' => true, 'kendaaswin' => true, 'kinoomaage' => true,
        'naaknigewin' => true, 'gakina' => true, 'mnis' => true, 'edbendaagzijig' => true,
        'binojiinh' => true, 'gamik' => true, 'ogemah' => true,
    ];

    /**
     * Substrings that mark a string as a proper noun / named institution rather
     * than generic nav - categorised "other" (kept, but not counted as nav demand).
     *
     * @var list<string>
     */
    private const array PROPER_NOUN_MARKERS = [
        'first nation', 'treaty', 'anishinabek', 'police service', 'facebook',
        'twitter', 'instagram', 'youtube', 'covid', 'canada',
    ];

    /** English stopwords ignored when deciding if a string is wholly Anishinaabemowin. */
    private const array STOPWORDS = ['and', 'the', 'of', 'a', 'an', 'to', 'in', 'for', 'our', 'us'];

    /** Drop the string entirely (theme noise, date strings, empties). */
    public static function shouldDrop(string $s): bool
    {
        $key = self::fold($s);
        if ($key === '') {
            return true;
        }

        return in_array($key, self::HARD_DROP, true) || self::isDateNoise($key);
    }

    /** A string already in Anishinaabemowin (every content token is in the lexicon). */
    public static function isAnishinaabemowin(string $s): bool
    {
        $tokens = self::contentTokens($s);
        if ($tokens === []) {
            return false;
        }
        foreach ($tokens as $t) {
            if (!isset(self::OJIBWE_LEXICON[$t])) {
                return false;
            }
        }

        return true;
    }

    /** governance-nav | global-ui | other. Assumes the string already passed drop/Ojibwe filters. */
    public static function categoryFor(string $s): string
    {
        if (in_array(self::fold($s), self::GLOBAL_UI, true)) {
            return 'global-ui';
        }
        $key = self::fold($s);
        foreach (self::PROPER_NOUN_MARKERS as $marker) {
            if (str_contains($key, $marker)) {
                return 'other';
            }
        }

        return 'governance-nav';
    }

    /**
     * Cluster key for ranking: normalise & <-> and, strip a leading "Our" / trailing
     * "Us", drop required-field markers, casefold. Surface forms keep their own row;
     * this groups them (e.g. "Contact" + "Contact Us", "Chief & Council" + "Chief and
     * Council", "History" + "Our History").
     */
    public static function conceptKey(string $s): string
    {
        $k = self::fold($s);
        $k = str_replace(' & ', ' and ', $k);
        $k = str_replace('&', ' and ', $k);
        $k = preg_replace('/\s+/', ' ', $k) ?? $k;
        $k = trim($k);
        if (str_starts_with($k, 'our ')) {
            $k = substr($k, 4);
        }
        if (str_ends_with($k, ' us')) {
            $k = substr($k, 0, -3);
        }

        return trim($k);
    }

    /** Casefold + collapse whitespace + strip surrounding punctuation/markers. */
    public static function fold(string $s): string
    {
        $s = str_replace('*', '', $s);
        $s = preg_replace('/\(\s*required\s*\)/i', '', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        $s = trim($s, " \t\r\n.,;:!?\"'");

        return mb_strtolower(trim($s));
    }

    /** Bare month / month-year / relative-date strings (calendar widgets). */
    private static function isDateNoise(string $key): bool
    {
        $months = 'january|february|march|april|may|june|july|august|september|october|november|december';

        return preg_match('/^(' . $months . ')(\s+\d{4})?$/', $key) === 1
            || preg_match('/^\d{1,2}\s+(' . $months . ')/', $key) === 1;
    }

    /**
     * Lower-cased content tokens with stopwords and non-letters removed.
     *
     * @return list<string>
     */
    private static function contentTokens(string $s): array
    {
        $clean = preg_replace('/[^\p{L}\s-]/u', ' ', mb_strtolower($s)) ?? '';
        $out = [];
        foreach (preg_split('/[\s-]+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $tok) {
            if (!in_array($tok, self::STOPWORDS, true)) {
                $out[] = $tok;
            }
        }

        return $out;
    }
}
