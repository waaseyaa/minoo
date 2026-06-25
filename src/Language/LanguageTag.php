<?php

declare(strict_types=1);

namespace App\Language;

/**
 * A parsed BCP 47 language tag in the Minoo contract (issue #898).
 *
 * Three layers: the language `oj` (autonym Anishinaabemowin), an optional dialect
 * middle subtag (an ISO 639-3 code such as `ojg` or `otw`), and an optional
 * community provenance carried as a BCP 47 private-use subtag `-x-<community>`.
 * Examples: `oj`, `oj-ojg`, `oj-x-sagamok`, `oj-otw-x-wikwemikong`.
 *
 * Parsing is the single well-formedness gate. Membership questions (which dialect
 * a community belongs to) are derived elsewhere by {@see DialectCodeProvider};
 * this class only validates structure and exposes the parts.
 */
final readonly class LanguageTag
{
    private function __construct(
        public string $language,
        public ?string $dialectSubtag,
        public ?string $community,
        public string $canonical,
    ) {
    }

    /**
     * Parse a raw tag, or return null when it is not a well-formed Minoo tag.
     * Case-insensitive; the canonical form is lowercased.
     *
     * @param list<string> $validDialectSubtags the recognized dialect middle codes
     */
    public static function parse(string $raw, array $validDialectSubtags): ?self
    {
        $tag = strtolower(trim($raw));
        if ($tag === '') {
            return null;
        }

        $community = null;
        $langPart = $tag;
        if (str_contains($tag, '-x-')) {
            [$langPart, $private] = explode('-x-', $tag, 2);
            // Private-use subtags: one or more 1-to-8 char alphanumeric pieces.
            if (preg_match('/^[a-z0-9]{1,8}(-[a-z0-9]{1,8})*$/', $private) !== 1) {
                return null;
            }
            $community = $private;
        } elseif ($tag === 'x' || str_ends_with($tag, '-x')) {
            // A dangling private-use singleton with nothing after it.
            return null;
        }

        $parts = explode('-', $langPart);
        if ($parts[0] !== 'oj') {
            return null;
        }

        $dialectSubtag = null;
        if (count($parts) === 2) {
            $dialectSubtag = $parts[1];
            if (!in_array($dialectSubtag, $validDialectSubtags, true)) {
                return null;
            }
        } elseif (count($parts) > 2) {
            // More than one subtag before -x- is not part of the contract.
            return null;
        }

        $canonical = 'oj'
            . ($dialectSubtag !== null ? '-' . $dialectSubtag : '')
            . ($community !== null ? '-x-' . $community : '');

        return new self('oj', $dialectSubtag, $community, $canonical);
    }
}
