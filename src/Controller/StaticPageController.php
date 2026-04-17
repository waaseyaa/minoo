<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;

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
    ) {}

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function about(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/about.html.twig', '/about', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function dataSovereignty(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/data-sovereignty.html.twig', '/data-sovereignty', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function elders(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('elders.html.twig', '/elders', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function games(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('games.html.twig', '/games', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function getInvolved(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/get-involved.html.twig', '/get-involved', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function howItWorks(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/how-it-works.html.twig', '/how-it-works', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function journey(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/journey.html.twig', '/journey', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function legal(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/legal/index.html.twig', $request->getPathInfo(), $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function matcher(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/matcher.html.twig', '/matcher', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function messages(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/messages.html.twig', '/messages', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function safety(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/safety.html.twig', '/safety', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function search(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/search/index.html.twig', '/search', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function studio(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/studio.html.twig', '/studio', $account);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function volunteer(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        return $this->render('pages/static/volunteer.html.twig', '/volunteer', $account);
    }

    private function render(string $template, string $path, AccountInterface $account): Response
    {
        $html = $this->twig->render($template, LayoutTwigContext::withAccount($account, [
            'path' => $path,
        ]));
        return new Response($html);
    }
}
