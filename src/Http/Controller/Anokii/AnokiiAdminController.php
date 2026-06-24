<?php

declare(strict_types=1);

namespace App\Http\Controller\Anokii;

use App\Anokii\Pipeline\PipelineCounts;
use App\Http\View\AnokiiShellContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * Anokii admin shell landing (#851).
 *
 * Renders the themed Anokii workspace chrome at /admin/anokii. The route is
 * role-gated (admin / elder_coordinator) by {@see \App\Provider\Routing\AnokiiRouteProvider},
 * so by the time this runs the account is an authorised staff member. The page
 * extends the Anokii package shell via the `@anokii` namespace; it never forks it.
 * Sidebar nav + user chip come from {@see AnokiiShellContext}.
 */
final class AnokiiAdminController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {
    }

    public function index(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        // The Overview is the workspace home; any unknown /admin/anokii/{sub}
        // falls through here too, so keep it the live pipeline dashboard.
        $snapshot = (new PipelineCounts($this->entityTypeManager))->compute();

        $html = $this->twig->render('pages/anokii/index.html.twig', AnokiiShellContext::build($account, 'home', [
            'path' => $request->getPathInfo(),
            'pipeline' => $snapshot,
            'counts' => $snapshot['counts'],
            'total' => $snapshot['total'],
            'do_next' => $snapshot['do_next'],
        ]));

        return new Response($html);
    }
}
