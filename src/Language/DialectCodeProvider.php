<?php

declare(strict_types=1);

namespace App\Language;

use App\Seed\ConfigSeeder;

/**
 * The single seam for the BCP 47 language-tag contract (issues #892, #898).
 *
 * Validates Minoo language tags (`oj`, optional dialect middle code, optional
 * `-x-<community>` provenance) and derives the dialect grouping for a tag without
 * ever storing it in the tag's place. Backed by {@see ConfigSeeder::dialectRegions()}
 * (the dialect groupings) and {@see ConfigSeeder::communityDialects()} (community
 * membership), so the backing can move to a jonesrussell/indigenous-taxonomy
 * package later without rewriting callers (deferred; see the tracker A.3 and the
 * Language tags section).
 */
final class DialectCodeProvider
{
    private const string LANGUAGE = 'oj';

    private const string AUTONYM = 'Anishinaabemowin';

    /**
     * Dialect middle subtags that derive a grouping spanning more than one ISO
     * code, beyond the per-region iso_639_3. Nishnaabemwin is the Eastern Ojibwe /
     * Odawa continuum (otw + ojg): ojg is the grouping's region iso, otw is added
     * here so a tag like `oj-otw` derives the same grouping.
     *
     * @var array<string, string>
     */
    private const array SPANNING_SUBTAGS = ['otw' => 'nishnaabemwin'];

    /**
     * Whether a tag is a well-formed Minoo BCP 47 tag.
     */
    public function isValid(string $tag): bool
    {
        return LanguageTag::parse($tag, $this->dialectSubtags()) !== null;
    }

    /**
     * The recognized non-community tags: the bare language plus each dialect-level
     * tag `oj-<iso>`. Community tags (`oj-x-<community>`) are open provenance and
     * are validated structurally rather than enumerated here.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        $codes = [self::LANGUAGE];
        foreach ($this->dialectSubtags() as $iso) {
            $codes[] = self::LANGUAGE . '-' . $iso;
        }

        return $codes;
    }

    /**
     * The valid dialect middle subtags (ISO 639-3 codes) a tag may carry.
     *
     * @return list<string>
     */
    public function dialectSubtags(): array
    {
        return [
            ...array_column(ConfigSeeder::dialectRegions(), 'iso_639_3'),
            ...array_keys(self::SPANNING_SUBTAGS),
        ];
    }

    /**
     * The dialect groupings for display and selection, each with its dialect-level
     * tag, internal code, endonym, English display name, and ISO 639-3 code.
     *
     * @return list<array{tag: string, code: string, name: string, display_name: string, iso_639_3: string}>
     */
    public function all(): array
    {
        return array_map(
            static fn (array $row): array => [
                'tag' => self::LANGUAGE . '-' . $row['iso_639_3'],
                'code' => $row['code'],
                'name' => $row['name'],
                'display_name' => $row['display_name'],
                'iso_639_3' => $row['iso_639_3'],
            ],
            ConfigSeeder::dialectRegions(),
        );
    }

    /**
     * The dialect grouping code (e.g. `nishnaabemwin`) derived from a tag, or null
     * for the bare language, a malformed tag, or a community with no confirmed
     * membership. Derivation only; nothing is stored.
     */
    public function dialectCodeForTag(string $tag): ?string
    {
        $parsed = LanguageTag::parse($tag, $this->dialectSubtags());
        if ($parsed === null) {
            return null;
        }

        if ($parsed->dialectSubtag !== null) {
            if (isset(self::SPANNING_SUBTAGS[$parsed->dialectSubtag])) {
                return self::SPANNING_SUBTAGS[$parsed->dialectSubtag];
            }
            foreach (ConfigSeeder::dialectRegions() as $row) {
                if ($row['iso_639_3'] === $parsed->dialectSubtag) {
                    return $row['code'];
                }
            }

            return null;
        }

        if ($parsed->community !== null) {
            return ConfigSeeder::communityDialects()[$parsed->community] ?? null;
        }

        return null;
    }

    /**
     * The dialect grouping row for a tag, or null when none is derivable.
     *
     * @return array{code: string, name: string, display_name: string, iso_639_3: string}|null
     */
    public function dialectFor(string $tag): ?array
    {
        $code = $this->dialectCodeForTag($tag);
        if ($code === null) {
            return null;
        }

        foreach (ConfigSeeder::dialectRegions() as $row) {
            if ($row['code'] === $code) {
                return [
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'display_name' => $row['display_name'],
                    'iso_639_3' => $row['iso_639_3'],
                ];
            }
        }

        return null;
    }

    /**
     * A human label for a tag, e.g. "Nishnaabemwin (Sagamok)" for `oj-x-sagamok`,
     * "Eastern Ojibwe" for `oj-ojg`, "Anishinaabemowin" for `oj`. Returns the raw
     * tag for a malformed input.
     */
    public function label(string $tag): string
    {
        $parsed = LanguageTag::parse($tag, $this->dialectSubtags());
        if ($parsed === null) {
            return $tag;
        }

        $grouping = $this->dialectFor($tag);
        $groupingName = $grouping !== null ? $grouping['name'] : self::AUTONYM;

        if ($parsed->community !== null) {
            return $groupingName . ' (' . $this->communityLabel($parsed->community) . ')';
        }

        if ($grouping !== null) {
            return $grouping['display_name'];
        }

        return self::AUTONYM;
    }

    private function communityLabel(string $community): string
    {
        return ucwords(str_replace('-', ' ', $community));
    }
}
