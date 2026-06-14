<?php

declare(strict_types=1);

namespace App\Http\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Dictionary display helpers.
 *
 * - clean_definition: the `definition` field is stored as a JSON-encoded array
 *   (e.g. ["bear"], ["go...","go over there..."]) or an empty array []. This
 *   renders it as readable text in every view, expanding the Ojibwe People's
 *   Dictionary abbreviations. Mirrors GameControllerTrait::cleanDefinition so
 *   the dictionary and the games show identical text.
 * - dialect_label: a truthful dialect name for an entry. Every current entry is
 *   from the Ojibwe People's Dictionary, which is Southwestern Ojibwe; future
 *   Nishnaabemwin (Eastern Ojibwe / Odawa) entries carry their own code. We
 *   never silently mix dialects, so the label is always shown.
 */
final class LanguageTwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('clean_definition', $this->cleanDefinition(...)),
            new TwigFilter('dialect_label', $this->dialectLabel(...)),
        ];
    }

    public function cleanDefinition(mixed $raw): string
    {
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $str = (string) $raw;
            if ($str === '') {
                return '';
            }
            $decoded = json_decode($str, true);
            $parts = is_array($decoded) ? $decoded : [$str];
        }

        $clean = implode('; ', array_filter(
            array_map(static fn ($p): string => trim((string) $p), $parts),
            static fn (string $p): bool => $p !== '',
        ));

        if ($clean === '') {
            return '';
        }

        // Expand OPD linguistic abbreviations for readability (longer first).
        return str_replace(
            ['h/self', 's/he', 'h/', 's.t.', 's.o.'],
            ['himself/herself', 'she/he', 'him/her', 'something', 'someone'],
            $clean,
        );
    }

    public function dialectLabel(?string $languageCode, string $attributionSource = ''): string
    {
        $source = strtolower($attributionSource);
        if (str_contains($source, 'ojibwe people') || $source === 'opd') {
            return 'Southwestern Ojibwe';
        }

        return match (strtolower((string) $languageCode)) {
            'ojg', 'oj-e', 'oj-east' => 'Eastern Ojibwe',
            'otw', 'oj-odawa' => 'Odawa',
            'oj-sw', 'ciw' => 'Southwestern Ojibwe',
            default => 'Anishinaabemowin',
        };
    }
}
