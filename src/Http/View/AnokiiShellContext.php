<?php

declare(strict_types=1);

namespace App\Http\View;

use App\Anokii\Pipeline\PipelineStage;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\User\User;

/**
 * Shared Twig context for pages rendered into the Anokii shell (#851, #853).
 *
 * Centralises the sidebar nav and the user-chip fields so every workspace tab
 * (Overview, Transcribe, …) renders identical chrome. Tabs pass their own
 * `nav_active` id and merge page-specific keys via $extra.
 */
final class AnokiiShellContext
{
    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    public static function build(AccountInterface $account, string $active, array $extra = []): array
    {
        $label = self::accountLabel($account);

        // When a controller passes a `pipeline` snapshot (App\Anokii\Pipeline\
        // PipelineCounts::compute()), expand it into a thin breadcrumb/progress
        // bar shown across every tab so the workspace reads as one flow (#876).
        if (isset($extra['pipeline']) && is_array($extra['pipeline'])) {
            $extra['pipeline_bar'] = self::pipelineBar(
                $extra['pipeline']['counts'] ?? [],
                (string) ($extra['pipeline_active'] ?? ''),
            );
        }

        return $extra + [
            'nav' => self::nav(),
            'nav_active' => $active,
            'home_path' => '/admin/anokii',
            'logout_path' => '/logout',
            'user_label' => $label,
            'user_role' => self::accountRole($account),
            'user_initials' => self::initials($label),
        ];
    }

    /**
     * Sidebar nav in pipeline flow order (#876): Ingest -> Transcribe -> Curate
     * -> Lessons. The Overview funnel is the workspace home.
     *
     * @return list<array{id: string, label: string, href: string, group?: string, badge?: string}>
     */
    private static function nav(): array
    {
        return [
            ['id' => 'home', 'label' => 'Overview', 'href' => '/admin/anokii', 'group' => 'Workspace'],
            ['id' => 'ingest', 'label' => 'Ingest', 'href' => '/admin/anokii/ingest', 'group' => 'Pipeline'],
            ['id' => 'transcribe', 'label' => 'Transcribe', 'href' => '/admin/anokii/transcribe'],
            ['id' => 'curate', 'label' => 'Curate', 'href' => '/admin/anokii/curate'],
            ['id' => 'lessons', 'label' => 'Lessons', 'href' => '/lessons', 'group' => 'Publish'],
        ];
    }

    /**
     * The cross-tab pipeline breadcrumb: one chip per stage with its live count,
     * the current stage highlighted.
     *
     * @param array<string, int> $counts
     *
     * @return list<array{id: string, label: string, count: int, href: string, active: bool}>
     */
    private static function pipelineBar(array $counts, string $active): array
    {
        $bar = [];
        foreach (PipelineStage::ORDER as $stage) {
            $bar[] = [
                'id' => $stage,
                'label' => PipelineStage::label($stage),
                'count' => (int) ($counts[$stage] ?? 0),
                'href' => PipelineStage::href($stage),
                'active' => $stage === $active,
            ];
        }

        return $bar;
    }

    private static function accountLabel(AccountInterface $account): string
    {
        if ($account instanceof User) {
            $name = trim($account->getName());
            if ($name !== '') {
                return $name;
            }
            $email = trim($account->getEmail());
            if ($email !== '') {
                return $email;
            }
        }

        return 'Staff';
    }

    private static function accountRole(AccountInterface $account): string
    {
        $roles = $account->getRoles();

        return $roles !== [] ? (string) reset($roles) : 'staff';
    }

    private static function initials(string $label): string
    {
        $parts = preg_split('/[\s@._-]+/', $label, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';
        foreach ($parts as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
            if (strlen($initials) === 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : '?';
    }
}
