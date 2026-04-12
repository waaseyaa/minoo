<?php

declare(strict_types=1);

namespace App\Support;

final class MatcherEngine
{
    private const POS_ABBREVIATIONS = [
        'na', 'nad', 'ni', 'nid', 'vai', 'vii', 'vta', 'vti',
        'pc', 'adv', 'pron', 'conj', 'interj', 'num',
    ];

    public static function pairCount(string $difficulty): int
    {
        return match ($difficulty) {
            'easy' => 4,
            'medium' => 6,
            'hard' => 8,
            default => 4,
        };
    }

    /**
     * Validate whether two IDs form a correct match.
     *
     * Both left and right refer to the same pair's `id` field when correct.
     * The frontend sends the dictionary entry ID from each side.
     *
     * @param string $leftId  The ID selected on the Ojibwe side
     * @param string $rightId The ID selected on the English side
     * @param list<array{id: string, ojibwe: string, english: string}> $pairs
     * @return array{correct: bool}
     */
    public static function validateMatch(string $leftId, string $rightId, array $pairs): array
    {
        return ['correct' => $leftId === $rightId];
    }

    public static function dailySeed(string $date): int
    {
        return crc32("matcher-{$date}");
    }

    /**
     * Extract a clean definition string from a field that may be JSON-encoded.
     *
     * Replicates GameControllerTrait::cleanDefinition() as a static method
     * so the engine can be used without a controller instance.
     */
    public static function cleanDefinition(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = implode('; ', array_filter(array_map('trim', $decoded)));
        }

        $raw = str_replace(
            ['h/self', 's/he', 'h/', 's.t.', 's.o.'],
            ['himself/herself', 'she/he', 'him/her', 'something', 'someone'],
            $raw,
        );

        return $raw;
    }

    /**
     * Check if a definition string is only a linguistic part-of-speech abbreviation.
     */
    public static function isAbbreviationOnly(string $definition): bool
    {
        return in_array(strtolower(trim($definition)), self::POS_ABBREVIATIONS, true);
    }

    /**
     * Select pairs from a list of raw dictionary entry data.
     *
     * @param list<array{id: int, word: string, definition: string}> $entries Raw entry data
     * @param int $count Number of pairs to select
     * @param int|null $seed Deterministic seed for daily mode (null = random)
     * @return list<array{id: int, ojibwe: string, english: string}>
     */
    public static function selectPairs(array $entries, int $count, ?int $seed = null): array
    {
        // Filter: must have word, must have definition, definition must not be abbreviation-only
        $valid = [];
        $seenDefinitions = [];
        foreach ($entries as $entry) {
            if ($entry['word'] === '') {
                continue;
            }
            if ($entry['definition'] === '') {
                continue;
            }

            $cleaned = self::cleanDefinition($entry['definition']);
            if ($cleaned === '' || self::isAbbreviationOnly($cleaned)) {
                continue;
            }

            // Avoid duplicate definitions
            $defKey = strtolower($cleaned);
            if (isset($seenDefinitions[$defKey])) {
                continue;
            }
            $seenDefinitions[$defKey] = true;

            $valid[] = [
                'id' => $entry['id'],
                'ojibwe' => $entry['word'],
                'english' => $cleaned,
            ];
        }

        // Seed-based or random shuffle
        if ($seed !== null) {
            mt_srand($seed);
            usort($valid, fn() => mt_rand(-1, 1));
            mt_srand();
        } else {
            shuffle($valid);
        }

        return array_slice($valid, 0, $count);
    }
}
