<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\ElderIdentity;
use Minoo\Support\Flash;
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
        $html = $this->twig->render('account/home.html.twig', [
            'account' => $account,
            'roles' => $account->getRoles(),
            'is_elder' => $account instanceof User && ElderIdentity::isElder($account),
            'path' => '/account',
        ]);

        return new SsrResponse(content: $html);
    }

    public function toggleElder(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('user');
        $user = $storage->load($account->id());

        $isElder = ElderIdentity::isElder($user);
        ElderIdentity::setElder($user, !$isElder);
        $storage->save($user);

        $message = $isElder ? 'Elder status removed.' : 'You have identified as an Elder. Miigwech.';
        Flash::success($message);

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/account']);
    }
}
