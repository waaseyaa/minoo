<?php

declare(strict_types=1);

namespace App\Http\Controller\Anokii;

use App\Http\View\AnokiiShellContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
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
        private readonly Environment $twig,
    ) {
    }

    public function index(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $active = (string) ($params['sub'] ?? 'home');

        $html = $this->twig->render('pages/anokii/index.html.twig', AnokiiShellContext::build($account, $active, [
            'path' => $request->getPathInfo(),
        ]));

        return new Response($html);
    }
}
