<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Domain\Geo\Service\VolunteerRanker;
use Minoo\Support\Flash;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
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

        $ranker = new VolunteerRanker($this->entityTypeManager);
        $rankedByRequest = $this->buildRankedMap($ranker, array_merge($open, $assigned), $volunteers);

        $communityNames = $this->buildCommunityNameMap(
            array_merge($allRequests, array_values($volunteers)),
        );
        $flash = Flash::consume();

        $html = $this->twig->render('dashboard/coordinator.html.twig', [
            'open_requests' => $open,
            'assigned_requests' => $assigned,
            'pending_confirmation' => $pendingConfirmation,
            'confirmed_requests' => $confirmed,
            'volunteers' => $volunteers,
            'ranked_by_request' => $rankedByRequest,
            'cancelled_requests' => $cancelled,
            'community_names' => $communityNames,
            'flash' => $flash,
        ]);

        return new SsrResponse(content: $html);
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
