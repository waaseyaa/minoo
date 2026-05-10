<?php

declare(strict_types=1);

namespace App\Support\Cli;

use App\Support\CrisisIncidentResolver;
use App\Support\CrisisOgAutomationPolicy;
use App\Support\CrisisOgImageService;
use App\Support\CrisisResolveContext;
use Waaseyaa\CLI\CliIO;

final class CrisisOgAssetsHandler
{
    public function __construct(
        private readonly CrisisIncidentResolver $crisisIncidentResolver,
        private readonly CrisisOgImageService $crisisOgImageService,
    ) {
    }

    public function execute(CliIO $io): int
    {
        $operation = strtolower(trim((string) ($io->argument('operation') ?? 'build')));

        if ($operation === 'regenerate') {
            return $this->executeRegenerate($io);
        }

        if ($operation !== '' && $operation !== 'build') {
            $io->error('Unknown operation: ' . $operation . ' (use build or regenerate).');

            return 2;
        }

        return $this->executeBuild($io);
    }

    private function executeBuild(CliIO $io): int
    {
        if (!CrisisOgAutomationPolicy::globalOptIn()) {
            $io->writeln('MINOO_CRISIS_OG_AUTO is not enabled; batch build skipped (HTTP still serves dynamic PNGs).');

            return 0;
        }

        $dryRun = (bool) $io->option('dry-run');
        $force = (bool) $io->option('force');
        $only = trim((string) ($io->option('only') ?? ''));
        $includeDraft = (bool) $io->option('draft');
        $context = $includeDraft ? CrisisResolveContext::withDraftIncidents() : CrisisResolveContext::publicWeb();

        $registry = $this->crisisIncidentResolver->loadRegistry();
        $ran = 0;
        $skipped = 0;

        foreach ($registry as $row) {
            $community = (string) ($row['community_slug'] ?? '');
            $incident = (string) ($row['incident_slug'] ?? '');
            if ($community === '' || $incident === '') {
                continue;
            }

            if ($only !== '' && $only !== $community . '/' . $incident) {
                continue;
            }

            $resolved = $this->crisisIncidentResolver->resolve($community, $incident, $context);
            if ($resolved === null) {
                ++$skipped;
                $io->writeln("Skip {$community}/{$incident}: not resolvable.");

                continue;
            }

            $reason = CrisisOgAutomationPolicy::buildBatchIneligibilityReason($resolved['registry'], $resolved['incident']);
            if ($reason !== null) {
                ++$skipped;
                $io->writeln("Skip {$community}/{$incident}: {$reason}");

                continue;
            }

            $result = $this->crisisOgImageService->writeGeneratedPng(
                $community,
                $incident,
                $dryRun,
                $force,
                false,
                $includeDraft,
            );

            if ($result['ok'] === true) {
                ++$ran;
                $path = (string) ($result['path'] ?? '');
                $bytes = (int) ($result['bytes'] ?? 0);
                $reasonOk = isset($result['reason']) ? (string) $result['reason'] : '';
                if ($reasonOk === 'dry_run') {
                    $io->writeln("[dry-run] Would write {$path} ({$bytes} bytes)");
                } elseif ($reasonOk === 'static_exists') {
                    $io->writeln("Exists {$path} — use --force to overwrite.");
                } else {
                    $io->writeln("Wrote {$path} ({$bytes} bytes)");
                }
            } else {
                ++$skipped;
                $r = isset($result['reason']) ? (string) $result['reason'] : 'unknown';
                $io->writeln("Skip {$community}/{$incident}: {$r}");
            }
        }

        $io->writeln("Done. processed_ok={$ran} skipped_or_failed={$skipped}");

        return 0;
    }

    private function executeRegenerate(CliIO $io): int
    {
        $community = trim((string) ($io->argument('community_slug') ?? ''));
        $incident = trim((string) ($io->argument('incident_slug') ?? ''));
        if ($community === '' || $incident === '') {
            $io->error('regenerate requires community_slug and incident_slug arguments.');

            return 2;
        }

        $dryRun = (bool) $io->option('dry-run');
        $force = (bool) $io->option('force');
        $includeDraft = (bool) $io->option('draft');

        $result = $this->crisisOgImageService->writeGeneratedPng(
            $community,
            $incident,
            $dryRun,
            $force,
            true,
            $includeDraft,
        );

        if ($result['ok'] !== true) {
            $failedReason = isset($result['reason']) ? (string) $result['reason'] : 'unknown';
            $io->error('Failed: ' . $failedReason);

            return 1;
        }

        $path = (string) ($result['path'] ?? '');
        $bytes = (int) ($result['bytes'] ?? 0);
        $reasonOk = isset($result['reason']) ? (string) $result['reason'] : '';
        if ($reasonOk === 'dry_run') {
            $io->writeln("[dry-run] Would write {$path} ({$bytes} bytes)");
        } elseif ($reasonOk === 'static_exists') {
            $io->writeln("{$path} already exists — use --force to overwrite.");
        } else {
            $io->writeln("Regenerated {$path} ({$bytes} bytes)");
        }

        return 0;
    }
}
