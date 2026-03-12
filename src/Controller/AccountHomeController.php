<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\SsrResponse;

final class AccountHomeController
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('account/home.html.twig', [
            'account' => $account,
            'roles' => $account->getRoles(),
            'path' => '/account',
        ]);

        return new SsrResponse(content: $html);
    }
}
