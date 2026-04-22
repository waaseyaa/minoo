<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rules for crisis OG automation (build, dynamic fallback, background writes).
 *
 * @phpstan-type RegistryRow array<string, mixed>
 * @phpstan-type IncidentConfig array<string, mixed>
 */
final class CrisisOgAutomationPolicy
{
    public const MODE_BOTH = 'both';

    public const MODE_BUILD = 'build';

    public const MODE_FALLBACK = 'fallback';

    public static function globalOptIn(): bool
    {
        $raw = getenv('MINOO_CRISIS_OG_AUTO');
        if ($raw === false || trim((string) $raw) === '') {
            return false;
        }

        $filtered = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return ($filtered ?? false) === true;
    }

    public static function isManagedGeneratedWebPath(string $ogImagePath): bool
    {
        $ogImagePath = trim($ogImagePath);

        return $ogImagePath !== '' && (bool) preg_match('#^/og/crisis/[a-z0-9-]+\.png$#', $ogImagePath);
    }

    /**
     * @param RegistryRow $registryRow
     */
    public static function normalizeMode(array $registryRow): string
    {
        $mode = strtolower(trim((string) ($registryRow['og_generate_mode'] ?? self::MODE_BOTH)));
        if (!in_array($mode, [self::MODE_BOTH, self::MODE_BUILD, self::MODE_FALLBACK], true)) {
            return self::MODE_BOTH;
        }

        return $mode;
    }

    /**
     * @param RegistryRow $registryRow
     * @param IncidentConfig $incident
     *
     * @return non-empty-string|null null when the incident may be included in `crisis:og-assets` batch
     */
    public static function buildBatchIneligibilityReason(array $registryRow, array $incident): ?string
    {
        $base = self::baseIneligibilityReason($registryRow, $incident);
        if ($base !== null) {
            return $base;
        }

        $mode = self::normalizeMode($registryRow);
        if ($mode === self::MODE_FALLBACK) {
            return 'og_generate_mode_fallback';
        }

        return null;
    }

    /**
     * HTTP may render dynamically when the static PNG is missing. Does not require {@see self::globalOptIn()}
     * or `og_generate` so existing crisis cards keep working without env flags.
     *
     * @param RegistryRow $registryRow
     * @param IncidentConfig $incident
     *
     * @return non-empty-string|null null when dynamic GD render is allowed if the static file is absent
     */
    public static function httpDynamicWhenMissingIneligibilityReason(array $registryRow, array $incident): ?string
    {
        $path = trim((string) ($incident['og_image_path'] ?? ''));
        if ($path === '') {
            return 'empty_og_image_path';
        }

        if (!self::isManagedGeneratedWebPath($path)) {
            return 'maintainer_override_path';
        }

        $mode = self::normalizeMode($registryRow);
        if ($mode === self::MODE_BUILD) {
            return 'og_generate_mode_build_only';
        }

        return null;
    }

    /**
     * @param RegistryRow $registryRow
     * @param IncidentConfig $incident
     *
     * @return non-empty-string|null null when a background PNG write may be scheduled after a dynamic hit
     */
    public static function backgroundWriteIneligibilityReason(array $registryRow, array $incident): ?string
    {
        $base = self::baseIneligibilityReason($registryRow, $incident);
        if ($base !== null) {
            return $base;
        }

        if (self::normalizeMode($registryRow) === self::MODE_BUILD) {
            return 'og_generate_mode_build_only';
        }

        return null;
    }

    /**
     * @param RegistryRow $registryRow
     * @param IncidentConfig $incident
     *
     * @return non-empty-string|null null when eligible for any automation path
     */
    public static function baseIneligibilityReason(array $registryRow, array $incident): ?string
    {
        if (!self::globalOptIn()) {
            return 'opt_in_off';
        }

        if (!($registryRow['og_generate'] ?? false)) {
            return 'og_generate_false';
        }

        $path = trim((string) ($incident['og_image_path'] ?? ''));
        if ($path === '') {
            return 'empty_og_image_path';
        }

        if (!self::isManagedGeneratedWebPath($path)) {
            return 'maintainer_override_path';
        }

        return null;
    }

    /**
     * Manual `crisis:og-assets regenerate` — bypasses {@see self::globalOptIn()}; still refuses non-managed paths.
     *
     * @param IncidentConfig $incident
     *
     * @return non-empty-string|null null when a regenerate write may proceed
     */
    public static function manualRegenerateIneligibilityReason(array $incident): ?string
    {
        $path = trim((string) ($incident['og_image_path'] ?? ''));
        if ($path === '') {
            return 'empty_og_image_path';
        }

        if (!self::isManagedGeneratedWebPath($path)) {
            return 'maintainer_override_path';
        }

        return null;
    }
}
