<?php

declare(strict_types=1);

namespace App\Language;

use App\Seed\ConfigSeeder;

/**
 * The single seam for valid Anishinaabemowin (and related) dialect codes (issue
 * #892).
 *
 * Backed today by {@see ConfigSeeder::dialectRegions()}, the de facto canonical
 * contract that also seeds the dialect_region config entity. Callers (the future
 * /api/lang dialect parameter, ingestion mapping of NorthCloud's free-text
 * Language) depend on this provider, not on the seeder or a package, so the
 * backing can move to a jonesrussell/indigenous-taxonomy package later without
 * rewriting them. Package publication is deferred until multi-nation federation
 * needs it (see docs/anishinaabemowin-language-api-tracker.md A.3).
 */
final class DialectCodeProvider
{
    /**
     * The valid dialect codes, for example oji-east, oji-ottawa.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return array_column(ConfigSeeder::dialectRegions(), 'code');
    }

    /**
     * Whether a requested dialect code is one of the canonical codes.
     */
    public function isValid(string $code): bool
    {
        return in_array($code, $this->codes(), true);
    }

    /**
     * A lightweight view of the dialects for display and selection: the code, the
     * endonym, the English display name, and the ISO 639-3 code.
     *
     * @return list<array{code: string, name: string, display_name: string, iso_639_3: string}>
     */
    public function all(): array
    {
        return array_map(
            static fn (array $row): array => [
                'code' => $row['code'],
                'name' => $row['name'],
                'display_name' => $row['display_name'],
                'iso_639_3' => $row['iso_639_3'],
            ],
            ConfigSeeder::dialectRegions(),
        );
    }
}
