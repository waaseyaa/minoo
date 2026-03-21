<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Feed\FeedAssemblerInterface;
use Minoo\Feed\FeedContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\SsrResponse;

final class FeedController
{
    public function __construct(
        private readonly FeedAssemblerInterface $assembler,
        private readonly Environment $twig,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $ctx = $this->buildContext($request, $query);
        $response = $this->assembler->assemble($ctx);

        $html = $this->twig->render('feed.html.twig', [
            'path' => '/',
            'account' => $account,
            'response' => $response,
            'nextCursor' => $response->nextCursor,
            'activeFilter' => $response->activeFilter,
        ]);

        $headers = ['Content-Type' => 'text/html; charset=UTF-8'];

        if ($ctx->isFirstVisit) {
            $expires = gmdate('D, d M Y H:i:s T', time() + 86400 * 365);
            $headers['Set-Cookie'] = "minoo_fv=1; Path=/; Expires={$expires}; SameSite=Lax";
        }

        return new SsrResponse(content: $html, headers: $headers);
    }

    public function api(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $ctx = $this->buildContext($request, $query);
        $response = $this->assembler->assemble($ctx);

        $items = array_map(function ($item) {
            $data = $item->toArray();
            $data['html'] = $this->twig->render('components/feed-card.html.twig', ['item' => $item]);
            return $data;
        }, $response->items);

        $json = json_encode([
            'items' => $items,
            'nextCursor' => $response->nextCursor,
            'activeFilter' => $response->activeFilter,
        ], JSON_THROW_ON_ERROR);

        return new SsrResponse(
            content: $json,
            statusCode: 200,
            headers: ['Content-Type' => 'application/json'],
        );
    }

    public function explore(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $type = $query['type'] ?? 'all';
        $q = trim($query['q'] ?? '');

        $targets = [
            'businesses' => '/groups',
            'people' => '/people',
            'events' => '/events',
            'all' => '/groups',
        ];

        $target = $targets[$type] ?? '/groups';

        if ($q !== '') {
            $target .= '?' . http_build_query(['q' => $q]);
        }

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $target]);
    }

    private function buildContext(HttpRequest $request, array $query): FeedContext
    {
        $locationCookie = $request->cookies->get('minoo_loc');
        $lat = null;
        $lon = null;

        if ($locationCookie !== null) {
            try {
                $loc = json_decode($locationCookie, true, 4, JSON_THROW_ON_ERROR);
                $lat = isset($loc['lat']) ? (float) $loc['lat'] : null;
                $lon = isset($loc['lon']) ? (float) $loc['lon'] : null;
            } catch (\JsonException) {
                // Invalid cookie — ignore
            }
        }

        $isFirstVisit = $request->cookies->get('minoo_fv') === null;

        return new FeedContext(
            latitude: $lat,
            longitude: $lon,
            activeFilter: $query['filter'] ?? 'all',
            cursor: $query['cursor'] ?? null,
            limit: min((int) ($query['limit'] ?? 20), 50),
            isFirstVisit: $isFirstVisit,
            isAuthenticated: false,
        );
    }
}
