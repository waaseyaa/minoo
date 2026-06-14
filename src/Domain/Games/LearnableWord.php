<?php

declare(strict_types=1);

namespace App\Domain\Games;

/**
 * Shared "is this a good word to learn in a game?" heuristics (#793).
 *
 * The games used to pull the first ~500 dictionary rows, which are alphabetical
 * ("aa…") and surface obscure verb glosses and even capitalised proper nouns
 * like sacred ceremony names. These helpers keep selection toward concrete,
 * common, single-sense words and sample diversely across the whole dictionary.
 */
final class LearnableWord
{
    /** Part-of-speech abbreviations that are not real glosses. */
    private const POS_ABBREVIATIONS = [
        'na', 'nad', 'ni', 'nid', 'vai', 'vii', 'vta', 'vti',
        'pc', 'adv', 'pron', 'conj', 'interj', 'num',
    ];

    /** Longest first-sense (in words) we still consider "learnable". */
    private const MAX_SENSE_WORDS = 6;

    /**
     * @param string $word              The dictionary headword.
     * @param string $cleanedDefinition Definition already run through cleanDefinition().
     */
    public static function isLearnable(string $word, string $cleanedDefinition): bool
    {
        $word = trim($word);
        if ($word === '') {
            return false;
        }

        // Lowercase, single-token headwords. Capitalised entries are place names,
        // personal names, or culturally sensitive proper nouns (e.g. ceremony
        // names) — not casual vocabulary-game material.
        if (mb_strtolower($word) !== $word) {
            return false;
        }
        if (str_contains($word, ' ')) {
            return false;
        }
        $len = mb_strlen($word);
        if ($len < 3 || $len > 14) {
            return false;
        }
        if (preg_match('/[^a-z\'\-]/u', $word) === 1) {
            return false;
        }

        $def = trim($cleanedDefinition);
        if ($def === '') {
            return false;
        }
        // Judge the first sense only — keeps multi-sense words but stays concise.
        $firstSense = trim(explode(';', $def)[0]);
        if ($firstSense === '') {
            return false;
        }
        if (in_array(mb_strtolower($firstSense), self::POS_ABBREVIATIONS, true)) {
            return false;
        }
        $senseWords = preg_split('/\s+/', $firstSense) ?: [];
        if (count($senseWords) > self::MAX_SENSE_WORDS) {
            return false;
        }

        return true;
    }

    /**
     * Random, diverse subset of ids drawn from the WHOLE id list (not the first
     * N alphabetically). Returns the input unchanged when it already fits.
     *
     * @param list<int|string> $allIds
     * @return list<int|string>
     */
    public static function sampleIds(array $allIds, int $size): array
    {
        $count = count($allIds);
        if ($size <= 0 || $count <= $size) {
            return $allIds;
        }
        $keys = (array) array_rand($allIds, $size);
        $out = [];
        foreach ($keys as $k) {
            $out[] = $allIds[$k];
        }
        return $out;
    }
}
