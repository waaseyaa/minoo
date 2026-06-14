<?php

declare(strict_types=1);

namespace App\Http\Controller\Community;

use App\Domain\Community\MamaweswenNations;
use App\Http\View\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

/**
 * The community living-map (#797, #798, #799). Renders the seven Mamaweswen
 * nations from factual public data (App\Domain\Community\MamaweswenNations) on a
 * Leaflet map plus per-nation detail pages whose leadership links back to the
 * authoritative ISC First Nation Profile. No individual community-member
 * listings — those remain consent-gated (#800).
 */
final class CommunityController
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function index(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $nations = MamaweswenNations::all();

        // Minimal marker payload for the Leaflet map (no leadership rosters).
        $markers = array_map(static fn (array $n): array => [
            'slug' => $n['slug'],
            'name' => $n['name'],
            'lat' => $n['lat'],
            'lng' => $n['lng'],
            'reserve' => $n['reserve'],
            'anchor' => $n['anchor'],
        ], $nations);

        $html = $this->twig->render('pages/community/index.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/communities',
            'nations' => $nations,
            'map_markers' => json_encode($markers, JSON_THROW_ON_ERROR),
        ]));

        return new Response($html);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function show(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $nation = MamaweswenNations::bySlug($slug);

        if ($nation === null) {
            $html = $this->twig->render('pages/community/show.html.twig', LayoutTwigContext::withAccount($account, [
                'path' => '/communities/' . $slug,
                'nation' => null,
            ]));
            return new Response($html, 404);
        }

        $html = $this->twig->render('pages/community/show.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/communities/' . $slug,
            'nation' => $nation,
            'governance_url' => MamaweswenNations::governanceUrl($nation['band_number']),
            'leadership_as_of' => MamaweswenNations::AS_OF,
        ]));

        return new Response($html);
    }
}
