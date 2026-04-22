<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\I18n\TranslatorInterface;

/**
 * Crisis Open Graph PNG: EN copy, {@see OgImageRenderer}, optional static file under public/, CLI writes.
 */
final class CrisisOgImageService
{
    /** @var array{0: int, 1: int, 2: int} */
    private const ACCENT_CRISIS = [230, 57, 70];

    public function __construct(
        private readonly string $projectRoot,
        private readonly EntityTypeManager $entityTypeManager,
        private readonly OgImageRenderer $ogImageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly CrisisIncidentResolver $crisisIncidentResolver,
        private readonly LoggerInterface $logger,
    ) {}

    public function absolutePublicFilePath(string $webPath): string
    {
        return $this->projectRoot . '/public' . $webPath;
    }

    /**
     * @param array{registry: array<string, mixed>, incident: array<string, mixed>} $resolved
     */
    public function buildDynamicPngBinary(array $resolved, EntityInterface $community): string
    {
        $incident = $resolved['incident'];
        $name = (string) $community->get('name');
        $titleKey = (string) ($incident['title_key'] ?? '');
        if ($titleKey === '') {
            throw new RuntimeException('crisis_og_missing_title_key');
        }

        $title = $this->translator->trans($titleKey, ['community' => $name], 'en');
        $subtitleKey = trim((string) ($incident['og_subtitle_key'] ?? ''));
        $subtitle = $subtitleKey !== '' ? $this->translator->trans($subtitleKey, [], 'en') : '';
        $ctaKey = trim((string) ($incident['og_image_cta_key'] ?? ''));
        $imageCta = $ctaKey !== '' ? $this->translator->trans($ctaKey, [], 'en') : null;

        return $this->ogImageRenderer->renderPng(
            $title,
            $subtitle,
            self::ACCENT_CRISIS,
            OgImageRenderer::STYLE_EMERGENCY,
            $imageCta,
        );
    }

    public function readStaticPngIfPresent(string $webPath): ?string
    {
        $abs = $this->absolutePublicFilePath($webPath);
        if (!is_file($abs) || !is_readable($abs)) {
            return null;
        }

        $raw = file_get_contents($abs);

        return $raw === false ? null : $raw;
    }

    /**
     * @param array{registry: array<string, mixed>, incident: array<string, mixed>} $resolved
     */
    public function etagPayloadForDynamic(array $resolved, EntityInterface $community): string
    {
        $incident = $resolved['incident'];
        $name = (string) $community->get('name');
        $titleKey = (string) ($incident['title_key'] ?? '');
        $title = $titleKey !== '' ? $this->translator->trans($titleKey, ['community' => $name], 'en') : '';
        $subtitleKey = trim((string) ($incident['og_subtitle_key'] ?? ''));
        $subtitle = $subtitleKey !== '' ? $this->translator->trans($subtitleKey, [], 'en') : '';
        $ctaKey = trim((string) ($incident['og_image_cta_key'] ?? ''));
        $imageCta = $ctaKey !== '' ? $this->translator->trans($ctaKey, [], 'en') : '';
        $revision = (string) (int) ($incident['og_image_revision'] ?? 1);
        $emergency = !empty($incident['emergency_open_graph']);

        return $title . '|' . $subtitle . '|' . $imageCta . '|' . $revision . '|' . ($emergency ? '1' : '0');
    }

    /**
     * @param array{registry: array<string, mixed>, incident: array<string, mixed>} $resolved
     */
    public function etagForStaticFile(array $resolved, string $absolutePath): string
    {
        $incident = $resolved['incident'];
        $revision = (string) (int) ($incident['og_image_revision'] ?? 1);
        $mtime = is_file($absolutePath) ? (string) filemtime($absolutePath) : '0';
        $size = is_file($absolutePath) ? (string) filesize($absolutePath) : '0';

        return '"' . hash('sha256', $revision . '|' . $mtime . '|' . $size . '|' . $absolutePath) . '"';
    }

    /**
     * @return array{ok: bool, reason?: non-empty-string, path?: string, bytes?: int}
     */
    public function writeGeneratedPng(
        string $communitySlug,
        string $incidentSlug,
        bool $dryRun,
        bool $force,
        bool $manualRegenerate,
        bool $includeDraftIncidents,
    ): array {
        $context = $includeDraftIncidents ? CrisisResolveContext::withDraftIncidents() : CrisisResolveContext::publicWeb();
        $resolved = $this->crisisIncidentResolver->resolve($communitySlug, $incidentSlug, $context);
        if ($resolved === null) {
            $this->logger->warning('crisis_og_write_skipped', [
                'reason' => 'resolve_failed',
                'community_slug' => $communitySlug,
                'incident_slug' => $incidentSlug,
            ]);

            return ['ok' => false, 'reason' => 'resolve_failed'];
        }

        $incident = $resolved['incident'];
        $reason = $manualRegenerate
            ? CrisisOgAutomationPolicy::manualRegenerateIneligibilityReason($incident)
            : CrisisOgAutomationPolicy::buildBatchIneligibilityReason($resolved['registry'], $incident);
        if ($reason !== null) {
            $this->logger->notice('crisis_og_write_skipped', [
                'reason' => $reason,
                'community_slug' => $communitySlug,
                'incident_slug' => $incidentSlug,
            ]);

            return ['ok' => false, 'reason' => $reason];
        }

        $webPath = trim((string) ($incident['og_image_path'] ?? ''));
        $abs = $this->absolutePublicFilePath($webPath);
        if (!$force && is_file($abs)) {
            $this->logger->info('crisis_og_write_skipped', [
                'reason' => 'static_exists',
                'path' => $webPath,
                'community_slug' => $communitySlug,
                'incident_slug' => $incidentSlug,
            ]);

            return ['ok' => true, 'reason' => 'static_exists', 'path' => $webPath];
        }

        if ($dryRun) {
            $this->logger->info('crisis_og_write_dry_run', [
                'path' => $webPath,
                'community_slug' => $communitySlug,
                'incident_slug' => $incidentSlug,
            ]);

            return ['ok' => true, 'path' => $webPath, 'bytes' => 0, 'reason' => 'dry_run'];
        }

        $community = $this->loadPublishedCommunity($communitySlug);
        if ($community === null) {
            $this->logger->warning('crisis_og_write_skipped', [
                'reason' => 'community_not_found',
                'community_slug' => $communitySlug,
            ]);

            return ['ok' => false, 'reason' => 'community_not_found'];
        }

        if (!extension_loaded('gd')) {
            $this->logger->error('crisis_og_write_failed', ['reason' => 'gd_missing']);

            return ['ok' => false, 'reason' => 'gd_missing'];
        }

        $binary = $this->buildDynamicPngBinary($resolved, $community);
        $bytes = strlen($binary);

        $dir = dirname($abs);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->logger->error('crisis_og_write_failed', [
                'reason' => 'mkdir_failed',
                'dir' => $dir,
            ]);

            return ['ok' => false, 'reason' => 'mkdir_failed'];
        }

        $tmpPath = tempnam($dir, 'ogc_');
        if ($tmpPath === false) {
            $this->logger->error('crisis_og_write_failed', ['reason' => 'tempnam_failed']);

            return ['ok' => false, 'reason' => 'tempnam_failed'];
        }

        try {
            if (file_put_contents($tmpPath, $binary) === false) {
                $this->logger->error('crisis_og_write_failed', ['reason' => 'write_tmp_failed']);

                return ['ok' => false, 'reason' => 'write_tmp_failed'];
            }

            if (!chmod($tmpPath, 0644)) {
                // non-fatal
            }

            if (!rename($tmpPath, $abs)) {
                $this->logger->error('crisis_og_write_failed', ['reason' => 'rename_failed']);

                return ['ok' => false, 'reason' => 'rename_failed'];
            }

            $tmpPath = null;

            $this->logger->info('crisis_og_write_ok', [
                'path' => $webPath,
                'bytes' => $bytes,
                'community_slug' => $communitySlug,
                'incident_slug' => $incidentSlug,
            ]);

            return ['ok' => true, 'path' => $webPath, 'bytes' => $bytes];
        } finally {
            if ($tmpPath !== null && is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }

    }

    /**
     * Fire-and-forget subprocess to materialize the PNG (skipped in testing / when opt-in off).
     *
     * @param array{registry: array<string, mixed>, incident: array<string, mixed>} $resolved
     */
    public function dispatchBackgroundMaterialize(string $communitySlug, string $incidentSlug, array $resolved): void
    {
        if (getenv('APP_ENV') === 'testing') {
            return;
        }

        if (CrisisOgAutomationPolicy::backgroundWriteIneligibilityReason($resolved['registry'], $resolved['incident']) !== null) {
            return;
        }

        $lockPath = $this->projectRoot . '/storage/og-crisis-bg-' . hash('sha256', $communitySlug . '|' . $incidentSlug) . '.lock';
        $fh = fopen($lockPath, 'cb');
        if ($fh === false) {
            return;
        }

        try {
            if (!flock($fh, LOCK_EX | LOCK_NB)) {
                return;
            }

            $bin = $this->projectRoot . '/bin/waaseyaa';
            if (!is_file($bin)) {
                return;
            }

            $process = new Process(
                [\PHP_BINARY, $bin, 'crisis:og-assets', 'regenerate', $communitySlug, $incidentSlug],
                $this->projectRoot,
                null,
                null,
                60,
            );
            $process->disableOutput();
            $process->start();

            $this->logger->info('crisis_og_background_dispatched', [
                'community_slug' => $communitySlug,
                'incident_slug' => $incidentSlug,
            ]);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    public function loadPublishedCommunity(string $communitySlug): ?EntityInterface
    {
        $storage = $this->entityTypeManager->getStorage('community');
        $ids = $storage->getQuery()
            ->condition('slug', $communitySlug)
            ->condition('status', 1)
            ->execute();

        if ($ids === []) {
            return null;
        }

        return $storage->load(reset($ids));
    }
}
