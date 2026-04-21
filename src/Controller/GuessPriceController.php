<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;

/**
 * SSR page for the Guess the Price mini-game (no API, no GameSession).
 *
 * The Twig template is rendered explicitly (not via path-to-template resolution).
 * Routes {@code /games/guess-price} and {@code /guess-price} both hit this action.
 */
final class GuessPriceController
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function page(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('pages/games/guess-price.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => $request->getPathInfo(),
        ]));
        return new Response($html);
    }
}
