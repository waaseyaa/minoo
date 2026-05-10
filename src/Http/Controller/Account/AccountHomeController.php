<?php

declare(strict_types=1);

namespace App\Http\Controller\Account;

use App\Http\View\LayoutTwigContext;
use App\Identity\ElderIdentity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;
use Waaseyaa\SSR\Flash\Flash;
use Waaseyaa\User\User;

final class AccountHomeController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly EntityTypeManager $entityTypeManager,
    ) {
    }

    public function index(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('pages/account/index.html.twig', LayoutTwigContext::withAccount($account, [
            'roles' => $account->getRoles(),
            'is_elder' => $account instanceof User && ElderIdentity::isElder($account),
            'path' => '/account',
        ]));

        return new Response($html);
    }

    public function toggleElder(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('user');
        $user = $storage->load($account->id());

        if (!$user instanceof User) {
            return new RedirectResponse('/account');
        }

        $isElder = ElderIdentity::isElder($user);
        ElderIdentity::setElder($user, !$isElder);
        $storage->save($user);

        Flash::success($isElder ? 'Elder status removed.' : 'You have identified as an Elder. Miigwech.');

        return new RedirectResponse('/account');
    }
}
