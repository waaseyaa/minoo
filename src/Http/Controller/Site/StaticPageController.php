<?php

declare(strict_types=1);

namespace App\Http\Controller\Site;

use App\Http\View\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * Minimal render-only controller for pages that have no domain logic.
 *
 * Introduced in Phase 1 of the template/CSS restructure to replace the
 * framework `RenderController::tryRenderPathTemplate()` fallback for pages
 * that had no explicit route. Each method renders exactly one template with
 * the standard layout context — no business logic, no entity queries.
 */
final class StaticPageController
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function about(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/about.html.twig', '/about', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function dataSovereignty(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/data-sovereignty.html.twig', '/data-sovereignty', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function games(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/games/index.html.twig', '/games', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function journey(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/journey.html.twig', '/journey', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function legal(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/legal/index.html.twig', $request->getPathInfo(), $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function matcher(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/matcher.html.twig', '/matcher', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function search(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/search/index.html.twig', '/search', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function studio(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/studio.html.twig', '/studio', $account);
    }

    private function render(string $template, string $path, AccountInterface $account): Response
    {
        $html = $this->twig->render($template, LayoutTwigContext::withAccount($account, [
            'path' => $path,
        ]));
        return new Response($html);
    }
}
