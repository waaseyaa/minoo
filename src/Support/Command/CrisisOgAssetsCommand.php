<?php

declare(strict_types=1);

namespace App\Support\Command;

use App\Support\CrisisIncidentResolver;
use App\Support\CrisisOgAutomationPolicy;
use App\Support\CrisisOgImageService;
use App\Support\CrisisResolveContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'crisis:og-assets',
    description: 'Build or regenerate crisis Open Graph PNGs under public/ (managed /og/crisis/*.png only).',
)]
final class CrisisOgAssetsCommand extends Command
{
    public function __construct(
        private readonly CrisisIncidentResolver $crisisIncidentResolver,
        private readonly CrisisOgImageService $crisisOgImageService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('operation', InputArgument::OPTIONAL, 'build|regenerate', 'build')
            ->addArgument('community_slug', InputArgument::OPTIONAL, 'With regenerate: community slug')
            ->addArgument('incident_slug', InputArgument::OPTIONAL, 'With regenerate: incident slug')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print actions without writing files')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Limit build to community_slug/incident_slug')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing managed PNG')
            ->addOption('draft', null, InputOption::VALUE_NONE, 'Include draft incidents in resolver');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $operation = strtolower(trim((string) $input->getArgument('operation')));

        if ($operation === 'regenerate') {
            return $this->executeRegenerate($input, $output);
        }

        if ($operation !== '' && $operation !== 'build') {
            $output->writeln('<error>Unknown operation: ' . $operation . ' (use build or regenerate).</error>');

            return self::INVALID;
        }

        return $this->executeBuild($input, $output);
    }

    private function executeBuild(InputInterface $input, OutputInterface $output): int
    {
        if (!CrisisOgAutomationPolicy::globalOptIn()) {
            $output->writeln('<comment>MINOO_CRISIS_OG_AUTO is not enabled; batch build skipped (HTTP still serves dynamic PNGs).</comment>');

            return self::SUCCESS;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');
        $only = trim((string) $input->getOption('only'));
        $includeDraft = (bool) $input->getOption('draft');
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
                $output->writeln("<comment>Skip {$community}/{$incident}: not resolvable.</comment>");

                continue;
            }

            $reason = CrisisOgAutomationPolicy::buildBatchIneligibilityReason($resolved['registry'], $resolved['incident']);
            if ($reason !== null) {
                ++$skipped;
                $output->writeln("<comment>Skip {$community}/{$incident}: {$reason}</comment>");

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
                    $output->writeln("<info>[dry-run] Would write {$path} ({$bytes} bytes)</info>");
                } elseif ($reasonOk === 'static_exists') {
                    $output->writeln("<comment>Exists {$path} — use --force to overwrite.</comment>");
                } else {
                    $output->writeln("<info>Wrote {$path} ({$bytes} bytes)</info>");
                }
            } else {
                ++$skipped;
                $r = isset($result['reason']) ? (string) $result['reason'] : 'unknown';
                $output->writeln("<comment>Skip {$community}/{$incident}: {$r}</comment>");
            }
        }

        $output->writeln("<info>Done. processed_ok={$ran} skipped_or_failed={$skipped}</info>");

        return self::SUCCESS;
    }

    private function executeRegenerate(InputInterface $input, OutputInterface $output): int
    {
        $community = trim((string) $input->getArgument('community_slug'));
        $incident = trim((string) $input->getArgument('incident_slug'));
        if ($community === '' || $incident === '') {
            $output->writeln('<error>regenerate requires community_slug and incident_slug arguments.</error>');

            return self::INVALID;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');
        $includeDraft = (bool) $input->getOption('draft');

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
            $output->writeln('<error>Failed: ' . $failedReason . '</error>');

            return self::FAILURE;
        }

        $path = (string) ($result['path'] ?? '');
        $bytes = (int) ($result['bytes'] ?? 0);
        $reasonOk = isset($result['reason']) ? (string) $result['reason'] : '';
        if ($reasonOk === 'dry_run') {
            $output->writeln("<info>[dry-run] Would write {$path} ({$bytes} bytes)</info>");
        } elseif ($reasonOk === 'static_exists') {
            $output->writeln("<comment>{$path} already exists — use --force to overwrite.</comment>");
        } else {
            $output->writeln("<info>Regenerated {$path} ({$bytes} bytes)</info>");
        }

        return self::SUCCESS;
    }
}
