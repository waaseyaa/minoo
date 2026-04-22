<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Loads {@see config/crisis_incidents.php} and merges registry rows with per-incident PHP returns.
 */
final class CrisisIncidentResolver
{
    public function __construct(
        private readonly string $projectRoot,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(string $communitySlug, string $incidentSlug, ?CrisisResolveContext $context = null): ?array
    {
        $context ??= CrisisResolveContext::publicWeb();
        $row = $this->findRegistryRow($communitySlug, $incidentSlug);
        if ($row === null) {
            return null;
        }

        if (($row['draft'] ?? false) && !$context->includeDrafts) {
            return null;
        }

        $configPath = (string) $row['config_path'];
        if ($configPath === '') {
            return null;
        }

        if (!str_starts_with($configPath, '/')) {
            $configPath = $this->projectRoot . '/' . ltrim($configPath, '/');
        }

        if (!is_file($configPath)) {
            return null;
        }

        /** @var array<string, mixed> $incident */
        $incident = require $configPath;

        return [
            'registry' => $row,
            'incident' => $incident,
        ];
    }

    /**
     * @return array{title_key: string, body_key: string, cta_key: string, href: string}|null
     */
    public function hubCalloutForCommunity(string $communitySlug, ?CrisisResolveContext $context = null): ?array
    {
        $context ??= CrisisResolveContext::publicWeb();
        $registry = $this->loadRegistry();
        foreach ($registry as $row) {
            if (($row['community_slug'] ?? '') !== $communitySlug) {
                continue;
            }
            if (!($row['show_on_community_hub'] ?? false)) {
                continue;
            }
            if (($row['draft'] ?? false) && !$context->includeDrafts) {
                continue;
            }
            $slug = (string) $row['incident_slug'];
            $titleKey = (string) ($row['hub_title_key'] ?? '');
            $bodyKey = (string) ($row['hub_body_key'] ?? '');
            $ctaKey = (string) ($row['hub_cta_key'] ?? '');
            if ($titleKey === '' || $bodyKey === '' || $ctaKey === '') {
                continue;
            }

            return [
                'title_key' => $titleKey,
                'body_key' => $bodyKey,
                'cta_key' => $ctaKey,
                'href' => '/communities/' . rawurlencode($communitySlug) . '/' . rawurlencode($slug),
            ];
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function loadRegistry(): array
    {
        $path = $this->projectRoot . '/config/crisis_incidents.php';
        if (!is_file($path)) {
            return [];
        }

        /** @var list<array<string, mixed>> $registry */
        $registry = require $path;

        return $registry;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRegistryRow(string $communitySlug, string $incidentSlug): ?array
    {
        foreach ($this->loadRegistry() as $row) {
            if (($row['community_slug'] ?? '') === $communitySlug && ($row['incident_slug'] ?? '') === $incidentSlug) {
                return $row;
            }
        }

        return null;
    }
}
