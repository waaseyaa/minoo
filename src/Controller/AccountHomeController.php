<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\ElderIdentity;
use Minoo\Support\LayoutTwigContext;
use Waaseyaa\SSR\Flash\Flash;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;
use Waaseyaa\User\User;

final class AccountHomeController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('account/home.html.twig', LayoutTwigContext::withAccount($account, [
            'roles' => $account->getRoles(),
            'is_elder' => $account instanceof User && ElderIdentity::isElder($account),
            'path' => '/account',
        ]));

        return new SsrResponse(content: $html);
    }

    public function toggleElder(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('user');
        $user = $storage->load($account->id());

        if (!$user instanceof User) {
            return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/account']);
        }

        $isElder = ElderIdentity::isElder($user);
        ElderIdentity::setElder($user, !$isElder);
        $storage->save($user);

        Flash::success($isElder ? 'Elder status removed.' : 'You have identified as an Elder. Miigwech.');

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/account']);
    }
}
