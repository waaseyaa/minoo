<?php

declare(strict_types=1);

namespace App\Http\View;

use Anokii\Admin\AdminModules;
use Anokii\Config\DistributionConfig;
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
     * Build the catalog dashboard context: the canonical Anokii module catalog
     * ({@see AdminModules}) split into the sidebar nav, the live tool cards, and
     * the product-preview cards. Live vs preview comes from DistributionConfig:
     * a module is live only when moduleEnabled() is true. The workspace home
     * ('dashboard') is always live so its nav entry points at /admin/anokii.
     *
     * Minoo keeps its own account model (AccountInterface), so this does not
     * route through the package Anokii\Admin\AdminShell (which needs a concrete
     * Waaseyaa\User\User); it replicates that class's trivial nav/tile split.
     *
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    public static function catalog(AccountInterface $account, DistributionConfig $distribution, array $extra = []): array
    {
        $modules = AdminModules::resolve(
            self::catalogLiveIds($distribution),
            self::catalogOverrides(),
            self::catalogExtraModules($distribution),
        );
        [$nav, $live, $preview] = self::splitCatalog($modules);

        return $extra + self::catalogChrome($account, 'dashboard', $nav) + [
            'live_cards' => $live,
            'preview_cards' => $preview,
        ];
    }

    /**
     * Build the product-preview ("coming soon") context for one catalog module,
     * or null when the id is not a catalog module.
     *
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>|null
     */
    public static function catalogComingSoon(AccountInterface $account, DistributionConfig $distribution, string $moduleId, array $extra = []): ?array
    {
        $module = AdminModules::find($moduleId);
        if ($module === null) {
            return null;
        }

        $modules = AdminModules::resolve(
            self::catalogLiveIds($distribution),
            self::catalogOverrides(),
            self::catalogExtraModules($distribution),
        );
        [$nav] = self::splitCatalog($modules);

        return $extra + self::catalogChrome($account, $moduleId, $nav) + ['module' => $module];
    }

    /**
     * Shared chrome (nav, user chip, brand, paths) for catalog pages.
     *
     * @param list<array<string, mixed>> $nav
     *
     * @return array<string, mixed>
     */
    private static function catalogChrome(AccountInterface $account, string $active, array $nav): array
    {
        $label = self::accountLabel($account);

        return [
            'nav' => $nav,
            'nav_active' => $active,
            'home_path' => '/admin/anokii',
            'logout_path' => '/logout',
            'brand_title' => 'Minoo',
            'brand_tag' => 'Language Workspace',
            'user_label' => $label,
            'user_role' => self::accountRole($account),
            'user_initials' => self::initials($label),
        ];
    }

    /**
     * The catalog ids that render as live: the workspace home always, plus any
     * module flagged on in DistributionConfig. Everything else renders as a
     * product-preview card.
     *
     * @return list<string>
     */
    private static function catalogLiveIds(DistributionConfig $distribution): array
    {
        $live = ['dashboard'];
        foreach (AdminModules::resolve([]) as $module) {
            $id = (string) $module['id'];
            if ($id !== 'dashboard' && $distribution->moduleEnabled($id)) {
                $live[] = $id;
            }
        }

        return $live;
    }

    /**
     * Install-specific catalog additions appended via AdminModules::resolve()'s
     * $extra. The `language` module is minoo's corpus pipeline, gated on
     * DistributionConfig like every other module: when enabled its card links to
     * the real admin tile at /admin/anokii/language, otherwise it shows as a
     * product-preview card. (#888)
     *
     * @return list<array<string, mixed>>
     */
    private static function catalogExtraModules(DistributionConfig $distribution): array
    {
        $enabled = $distribution->moduleEnabled('language');

        return [[
            'id' => 'language',
            'label' => 'Language',
            'group' => 'Workspace',
            'order' => 1,
            'live' => $enabled,
            'href' => $enabled ? '/admin/anokii/language' : '/admin/anokii/m/language',
            'desc' => 'Build the dictionary and lessons from the community corpus: ingest, transcribe, curate, publish.',
            'icon' => '<path d="M4 5h6a2 2 0 0 1 2 2v12a2 2 0 0 0-2-2H4V5Z" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linejoin="round"/><path d="M20 5h-6a2 2 0 0 0-2 2v12a2 2 0 0 1 2-2h6V5Z" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linejoin="round"/>',
            'badge' => $enabled ? '' : 'Preview',
            'tile' => true,
        ]];
    }

    /**
     * Per-install presentation overrides for canonical catalog modules. Pins the
     * workspace home first so the appended language module (order 1) slots in
     * right after it, keeping a single "Workspace" nav group header.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function catalogOverrides(): array
    {
        return ['dashboard' => ['order' => 0]];
    }

    /**
     * Split a resolved AdminModules set into [nav, live_cards, preview_cards],
     * mirroring Anokii\Admin\AdminShell so the package templates render as
     * designed while minoo keeps its own account context.
     *
     * @param list<array<string, mixed>> $modules
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>}
     */
    private static function splitCatalog(array $modules): array
    {
        $nav = [];
        $live = [];
        $preview = [];
        foreach ($modules as $m) {
            $nav[] = [
                'id' => $m['id'],
                'label' => $m['label'],
                'href' => $m['href'],
                'group' => $m['group'],
                'icon' => $m['icon'],
                'badge' => $m['badge'],
            ];
            if (($m['tile'] ?? false) !== true) {
                continue;
            }
            $card = [
                'label' => $m['label'],
                'href' => $m['href'],
                'desc' => $m['desc'],
                'icon' => $m['icon'],
                'badge' => $m['badge'],
            ];
            if (($m['live'] ?? false) === true) {
                $live[] = $card;
            } else {
                $preview[] = $card;
            }
        }

        return [$nav, $live, $preview];
    }

    /**
     * Sidebar nav in pipeline flow order (#876): Ingest -> Transcribe -> Curate
     * -> Lessons. Overview is the language module landing at /admin/anokii/language
     * (#888); the catalog dashboard is the workspace home reached via the brand.
     *
     * @return list<array{id: string, label: string, href: string, group?: string, badge?: string}>
     */
    private static function nav(): array
    {
        return [
            ['id' => 'home', 'label' => 'Overview', 'href' => '/admin/anokii/language', 'group' => 'Workspace'],
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
