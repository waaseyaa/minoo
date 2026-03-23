<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\Flash;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;
use Waaseyaa\User\User;

final class RoleManagementController
{
    private const ALLOWED_ACTIONS = ['grant', 'revoke'];
    private const ALLOWED_ROLES = ['volunteer', 'elder', 'coordinator'];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function changeRole(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $targetUid = (int) $params['uid'];
        $action = $request->request->get('action', '');
        $role = $request->request->get('role', '');
        $referrer = $request->headers->get('Referer', '/account');

        if (!in_array($action, self::ALLOWED_ACTIONS, true)
            || !in_array($role, self::ALLOWED_ROLES, true)) {
            Flash::error('Invalid request.');

            return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
        }

        if ($targetUid === $account->id()) {
            Flash::error('You cannot modify your own roles.');

            return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
        }

        $actorRoles = $account->getRoles();
        $isCoordinator = in_array('elder_coordinator', $actorRoles, true);
        $isAdmin = in_array('admin', $actorRoles, true);

        if (!$isCoordinator && !$isAdmin) {
            return new SsrResponse(content: '', statusCode: 403);
        }

        if ($role === 'coordinator' && !$isAdmin) {
            Flash::error('Only admins can manage coordinator roles.');

            return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
        }

        $storage = $this->entityTypeManager->getStorage('user');
        $user = $storage->load($targetUid);

        if ($user === null) {
            Flash::error('User not found.');

            return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
        }

        \assert($user instanceof User);

        if (in_array('admin', $user->getRoles(), true)) {
            Flash::error('Admin accounts cannot be modified.');

            return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
        }

        if ($role === 'elder') {
            $user->setElder($action === 'grant');
        } else {
            if ($action === 'grant') {
                $user->addRole($role);
            } else {
                $user->removeRole($role);
            }
        }
        $storage->save($user);

        $verb = $action === 'grant' ? 'granted to' : 'revoked from';
        Flash::success(ucfirst($role) . " role {$verb} " . $user->getName() . '.');

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $referrer]);
    }

    public function coordinatorList(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $users = $this->loadUserRows($account);

        $html = $this->twig->render('dashboard/coordinator-users.html.twig', [
            'users' => $users,
            'account' => $account,
            'can_manage_coordinator' => false,
            'path' => '/dashboard/coordinator/users',
        ]);

        return new SsrResponse(content: $html);
    }

    public function adminList(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $users = $this->loadUserRows($account);

        $html = $this->twig->render('admin/users.html.twig', [
            'users' => $users,
            'account' => $account,
            'can_manage_coordinator' => true,
            'path' => '/admin/users',
        ]);

        return new SsrResponse(content: $html);
    }

    /**
     * @return list<array{uid: int, name: string, email: string, roles: string[], is_elder: bool}>
     */
    private function loadUserRows(AccountInterface $account): array
    {
        $storage = $this->entityTypeManager->getStorage('user');
        $ids = $storage->getQuery()
            ->condition('status', 1)
            ->sort('name', 'ASC')
            ->execute();

        if ($ids === []) {
            return [];
        }

        $users = array_values($storage->loadMultiple($ids));
        $rows = [];

        foreach ($users as $user) {
            \assert($user instanceof User);

            if ($user->id() === $account->id()) {
                continue;
            }

            $rows[] = [
                'uid' => $user->id(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'is_elder' => $user->isElder(),
            ];
        }

        return $rows;
    }
}
