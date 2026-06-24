<?php

declare(strict_types=1);

namespace App\Http\Controller\Anokii;

use Anokii\Config\DistributionConfig;
use App\Http\View\AnokiiShellContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * Anokii admin shell landing (#851, catalog shell #886).
 *
 * Renders /admin/anokii as the canonical Anokii module catalog
 * ({@see \Anokii\Admin\AdminModules}), driven by {@see DistributionConfig}: a
 * module is a live tool only when moduleEnabled() is true, otherwise it shows as
 * a product-preview card linking to its coming-soon page. The route is role-gated
 * (admin / elder_coordinator) by {@see \App\Provider\Routing\AnokiiRouteProvider},
 * so the account is staff. The page extends the Anokii package admin templates via
 * the `@anokii` namespace; it never forks them. Catalog + chrome come from
 * {@see AnokiiShellContext}.
 *
 * The corpus pipeline pages (Ingest / Transcribe / Curate) keep their own routes
 * and chrome; this controller owns only the catalog dashboard and the per-module
 * coming-soon page.
 */
final class AnokiiAdminController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly DistributionConfig $distribution,
    ) {
    }

    public function index(AccountInterface $account): Response
    {
        $context = AnokiiShellContext::catalog($account, $this->distribution, [
            'hero_lead' => 'Your Minoo language workspace, running on the Anokii distribution.',
        ]);

        return new Response($this->twig->render('pages/anokii/dashboard.html.twig', $context));
    }

    /**
     * Product-preview ("coming soon") page for a catalog module not live on this
     * install. Unknown module ids fall back to the dashboard.
     *
     * @param array<string, mixed> $params
     */
    public function comingSoon(#[MapRoute] array $params, AccountInterface $account): Response
    {
        $moduleId = is_string($params['module'] ?? null) ? $params['module'] : '';
        $context = AnokiiShellContext::catalogComingSoon($account, $this->distribution, $moduleId);
        if ($context === null) {
            return new RedirectResponse('/admin/anokii');
        }

        return new Response($this->twig->render('pages/anokii/coming-soon.html.twig', $context));
    }
}
