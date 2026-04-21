<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\ElderIdentity;
use App\Support\LayoutTwigContext;
use Waaseyaa\SSR\Flash\Flash;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\User\User;

final class RoleManagementController
{
    private const ALLOWED_ACTIONS = ['grant', 'revoke'];
    private const ALLOWED_ROLES = ['volunteer', 'elder', 'elder_coordinator'];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function changeRole(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $targetUid = (int) $params['uid'];
        $action = $request->request->get('action', '');
        $role = $request->request->get('role', '');
        $referrer = self::safeReferrer($request);

        if (!in_array($action, self::ALLOWED_ACTIONS, true)
            || !in_array($role, self::ALLOWED_ROLES, true)) {
            Flash::error('Invalid request.');

            return new RedirectResponse($referrer);
        }

        if ($targetUid === $account->id()) {
            Flash::error('You cannot modify your own roles.');

            return new RedirectResponse($referrer);
        }

        $actorRoles = $account->getRoles();
        $isCoordinator = in_array('elder_coordinator', $actorRoles, true);
        $isAdmin = in_array('admin', $actorRoles, true);

        if (!$isCoordinator && !$isAdmin) {
            Flash::error('You do not have permission to manage roles.');

            return new RedirectResponse('/account');
        }

        if ($role === 'elder_coordinator' && !$isAdmin) {
            Flash::error('Only admins can manage coordinator roles.');

            return new RedirectResponse($referrer);
        }

        $storage = $this->entityTypeManager->getStorage('user');
        $user = $storage->load($targetUid);

        if (!$user instanceof User) {
            Flash::error('User not found.');

            return new RedirectResponse($referrer);
        }

        if (in_array('admin', $user->getRoles(), true)) {
            Flash::error('Admin accounts cannot be modified.');

            return new RedirectResponse($referrer);
        }

        try {
            if ($role === 'elder') {
                ElderIdentity::setElder($user, $action === 'grant');
            } else {
                if ($action === 'grant') {
                    $user->addRole($role);
                } else {
                    $user->removeRole($role);
                }
            }
            $storage->save($user);
        } catch (\Throwable $e) {
            error_log(sprintf('[RoleManagementController::changeRole] Error: %s', $e->getMessage()));
            Flash::error('Unable to update role. Please try again.');

            return new RedirectResponse($referrer);
        }

        $label = $role === 'elder_coordinator' ? 'Coordinator' : ucfirst($role);
        $verb = $action === 'grant' ? 'granted to' : 'revoked from';
        Flash::success("{$label} role {$verb} " . $user->getName() . '.');

        return new RedirectResponse($referrer);
    }

    public function coordinatorList(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $users = $this->loadUserRows($account);

        $html = $this->twig->render('pages/dashboard/coordinator-users.html.twig', LayoutTwigContext::withAccount($account, [
            'users' => $users,
            'can_manage_coordinator' => false,
            'path' => '/dashboard/coordinator/users',
        ]));

        return new Response($html);
    }

    public function adminList(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $users = $this->loadUserRows($account);

        $html = $this->twig->render('pages/admin/users.html.twig', LayoutTwigContext::withAccount($account, [
            'users' => $users,
            'can_manage_coordinator' => true,
            'path' => '/staff/users',
        ]));

        return new Response($html);
    }

    /**
     * @return list<array{uid: int, name: string, email: string, roles: string[], is_elder: bool}>
     */
    private function loadUserRows(AccountInterface $account): array
    {
        try {
            $storage = $this->entityTypeManager->getStorage('user');
            $ids = $storage->getQuery()
                ->condition('status', 1)
                ->sort('name', 'ASC')
                ->execute();
        } catch (\Throwable $e) {
            error_log(sprintf('[RoleManagementController::loadUserRows] Error: %s', $e->getMessage()));

            return [];
        }

        if ($ids === []) {
            return [];
        }

        $users = array_values($storage->loadMultiple($ids));
        $rows = [];

        foreach ($users as $user) {
            if (!$user instanceof User || $user->id() === $account->id()) {
                continue;
            }

            $rows[] = [
                'uid' => $user->id(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'is_elder' => ElderIdentity::isElder($user),
            ];
        }

        return $rows;
    }

    private static function safeReferrer(HttpRequest $request): string
    {
        $referrer = $request->headers->get('Referer', '/account');

        if (!is_string($referrer) || !str_starts_with($referrer, '/') || str_starts_with($referrer, '//')) {
            return '/account';
        }

        return $referrer;
    }
}
