<?php

declare(strict_types=1);

namespace App\Http\Controller\Account;

use App\Domain\Account\SavedWords;
use App\Http\View\LayoutTwigContext;
use App\Identity\ElderIdentity;
use App\Identity\HomeCommunityIdentity;
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
        $homeCommunityId = $account instanceof User ? HomeCommunityIdentity::getHomeCommunity($account) : null;
        $communities = $this->loadCommunities();

        $homeCommunityName = '';
        foreach ($communities as $community) {
            if ($community['id'] === $homeCommunityId) {
                $homeCommunityName = $community['name'];
                break;
            }
        }

        $html = $this->twig->render('pages/account/index.html.twig', LayoutTwigContext::withAccount($account, [
            'roles' => $account->getRoles(),
            'is_elder' => $account instanceof User && ElderIdentity::isElder($account),
            'saved_count' => SavedWords::countFor($this->entityTypeManager, $account),
            'home_community_id' => $homeCommunityId,
            'home_community_name' => $homeCommunityName,
            'communities' => $communities,
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

    /**
     * Self-select (or clear) the member's home community. Consent-first: an
     * empty value clears it; any other value is accepted only when it is a real
     * published community. Community-level only, no coordinates.
     */
    public function selectHomeCommunity(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('user');
        $user = $storage->load($account->id());

        if (!$user instanceof User) {
            return new RedirectResponse('/account');
        }

        $raw = trim((string) $request->request->get('home_community_id', ''));
        $communityId = $raw !== '' && ctype_digit($raw) ? (int) $raw : null;

        if ($communityId !== null && !$this->isPublishedCommunity($communityId)) {
            $communityId = null;
        }

        HomeCommunityIdentity::setHomeCommunity($user, $communityId);
        $storage->save($user);

        Flash::success($communityId === null ? 'Home community cleared.' : 'Home community saved. Miigwech.');

        return new RedirectResponse('/account');
    }

    /**
     * Published communities for the selector, sorted by name.
     *
     * @return list<array{id: int, name: string}>
     */
    private function loadCommunities(): array
    {
        if (!$this->entityTypeManager->hasDefinition('community')) {
            return [];
        }

        $storage = $this->entityTypeManager->getStorage('community');
        $ids = $storage->getQuery()->accessCheck(false)
            ->condition('status', 1)
            ->execute();

        if ($ids === []) {
            return [];
        }

        $communities = [];
        foreach ($storage->loadMultiple($ids) as $community) {
            $communities[] = [
                'id' => (int) $community->id(),
                'name' => (string) ($community->get('name') ?? $community->label()),
            ];
        }

        usort($communities, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $communities;
    }

    private function isPublishedCommunity(int $communityId): bool
    {
        if (!$this->entityTypeManager->hasDefinition('community')) {
            return false;
        }

        $community = $this->entityTypeManager->getStorage('community')->load($communityId);

        return $community !== null && (int) $community->get('status') === 1;
    }
}
