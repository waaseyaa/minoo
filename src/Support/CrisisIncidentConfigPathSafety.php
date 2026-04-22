<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ensures registry-supplied crisis config paths resolve to real files under {@see $projectRoot}/config/crisis/.
 */
final class CrisisIncidentConfigPathSafety
{
    /**
     * @return non-empty-string|null Absolute path safe to pass to {@see require}
     */
    public static function validatedAbsoluteConfigPath(string $projectRoot, string $rawPathFromRegistry): ?string
    {
        if ($rawPathFromRegistry === '' || str_contains($rawPathFromRegistry, "\0")) {
            return null;
        }

        $projectRoot = rtrim($projectRoot, "/\\");
        if ($projectRoot === '') {
            return null;
        }

        if (!str_starts_with($rawPathFromRegistry, '/')) {
            $absolute = $projectRoot . '/' . ltrim($rawPathFromRegistry, '/');
        } else {
            $absolute = $rawPathFromRegistry;
        }

        if (!is_file($absolute)) {
            return null;
        }

        $realFile = realpath($absolute);
        $crisisDir = $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'crisis';
        $realBase = realpath($crisisDir);
        if ($realFile === false || $realBase === false) {
            return null;
        }

        $normFile = str_replace('\\', '/', $realFile);
        $normBase = rtrim(str_replace('\\', '/', $realBase), '/');

        if ($normFile !== $normBase && !str_starts_with($normFile, $normBase . '/')) {
            return null;
        }

        return $realFile;
    }
}
