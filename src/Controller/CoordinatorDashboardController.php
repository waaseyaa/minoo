<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Domain\Geo\Service\VolunteerRanker;
use Minoo\Support\Flash;
use Minoo\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class CoordinatorDashboardController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $requestStorage = $this->entityTypeManager->getStorage('elder_support_request');

        $allIds = $requestStorage->getQuery()
            ->sort('created_at', 'DESC')
            ->execute();

        $allRequests = $allIds !== [] ? $requestStorage->loadMultiple($allIds) : [];

        $open = [];
        $assigned = [];
        $pendingConfirmation = [];
        $confirmed = [];
        $cancelled = [];

        foreach ($allRequests as $req) {
            match ($req->get('status')) {
                'open' => $open[] = $req,
                'assigned', 'in_progress' => $assigned[] = $req,
                'completed' => $pendingConfirmation[] = $req,
                'confirmed' => $confirmed[] = $req,
                'cancelled' => $cancelled[] = $req,
                default => null,
            };
        }

        $volunteerStorage = $this->entityTypeManager->getStorage('volunteer');
        $volunteerIds = $volunteerStorage->getQuery()
            ->condition('status', 'active')
            ->sort('name', 'ASC')
            ->execute();

        $volunteers = $volunteerIds !== [] ? $volunteerStorage->loadMultiple($volunteerIds) : [];
        $volunteersById = [];
        foreach ($volunteers as $volunteer) {
            $volunteersById[$volunteer->id()] = $volunteer;
        }

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $rankedByRequest = $this->buildRankedMap($ranker, array_merge($open, $assigned), $volunteers);

        $pendingApplicationIds = $volunteerStorage->getQuery()
            ->condition('status', 'pending')
            ->execute();
        $pendingApplicationCount = count($pendingApplicationIds);

        $communityNames = $this->buildCommunityNameMap(
            array_merge($allRequests, array_values($volunteers)),
        );
        $html = $this->twig->render('dashboard/coordinator.html.twig', LayoutTwigContext::withAccount($account, [
            'open_requests' => $open,
            'assigned_requests' => $assigned,
            'pending_confirmation' => $pendingConfirmation,
            'confirmed_requests' => $confirmed,
            'volunteers' => $volunteers,
            'volunteers_by_id' => $volunteersById,
            'ranked_by_request' => $rankedByRequest,
            'cancelled_requests' => $cancelled,
            'community_names' => $communityNames,
            'pending_application_count' => $pendingApplicationCount,
        ]));

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    public function applications(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('volunteer');
        $ids = $storage->getQuery()
            ->condition('status', 'pending')
            ->sort('created_at', 'DESC')
            ->execute();

        $applications = $ids !== [] ? $storage->loadMultiple($ids) : [];

        $html = $this->twig->render('dashboard/coordinator-applications.html.twig', LayoutTwigContext::withAccount($account, [
            'applications' => $applications,
        ]));

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    public function approveApplication(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $volunteer = $this->loadVolunteerByUuid($params['uuid'] ?? '');

        if ($volunteer === null || $volunteer->get('status') !== 'pending') {
            Flash::error('Application not found or already processed.');
            return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator/applications']);
        }

        $volunteer->set('status', 'active');
        $volunteer->set('updated_at', time());
        $this->entityTypeManager->getStorage('volunteer')->save($volunteer);

        // Grant volunteer role to linked user account
        $accountId = $volunteer->get('account_id');
        if ($accountId !== null && $accountId !== '' && is_numeric($accountId)) {
            $userStorage = $this->entityTypeManager->getStorage('user');
            /** @var \Waaseyaa\User\User|null $user */
            $user = $userStorage->load((int) $accountId);
            if ($user !== null) {
                $user->addRole('volunteer');
                $userStorage->save($user);
            }
        }

        Flash::success('Volunteer application approved.');
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator/applications']);
    }

    /** @param array<string, mixed> $params */
    public function denyApplication(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $volunteer = $this->loadVolunteerByUuid($params['uuid'] ?? '');

        if ($volunteer === null || $volunteer->get('status') !== 'pending') {
            Flash::error('Application not found or already processed.');
            return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator/applications']);
        }

        $volunteer->set('status', 'denied');
        $volunteer->set('updated_at', time());
        $this->entityTypeManager->getStorage('volunteer')->save($volunteer);

        Flash::info('Volunteer application denied.');
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator/applications']);
    }

    private function loadVolunteerByUuid(string $uuid): ?EntityInterface
    {
        if ($uuid === '') {
            return null;
        }

        $storage = $this->entityTypeManager->getStorage('volunteer');
        $ids = $storage->getQuery()->condition('uuid', $uuid)->execute();

        if ($ids === []) {
            return null;
        }

        return $storage->load(reset($ids));
    }

    /**
     * @param \Waaseyaa\Entity\ContentEntityBase[] $openRequests
     * @param \Waaseyaa\Entity\ContentEntityBase[] $volunteers
     * @return array<int|string, \Minoo\Domain\Geo\ValueObject\RankedVolunteer[]>
     */
    private function buildRankedMap(
        VolunteerRanker $ranker,
        array $openRequests,
        array $volunteers,
    ): array {
        $map = [];

        foreach ($openRequests as $req) {
            $map[$req->id()] = $ranker->rank($volunteers, $req);
        }

        return $map;
    }

    /**
     * Collect community IDs from entities and batch-load their names.
     *
     * @param \Waaseyaa\Entity\ContentEntityBase[] $entities
     * @return array<int|string, string>
     */
    private function buildCommunityNameMap(array $entities): array
    {
        $ids = [];
        foreach ($entities as $entity) {
            $ref = $entity->get('community');
            if ($ref !== null && $ref !== '' && is_numeric($ref)) {
                $ids[(int) $ref] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $storage = $this->entityTypeManager->getStorage('community');
        $communities = $storage->loadMultiple(array_keys($ids));

        $names = [];
        foreach ($communities as $community) {
            $names[$community->id()] = $community->get('name') ?? '';
        }

        return $names;
    }
}
