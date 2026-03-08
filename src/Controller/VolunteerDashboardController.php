<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class VolunteerDashboardController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $storage = $this->entityTypeManager->getStorage('elder_support_request');

        $ids = $storage->getQuery()
            ->condition('assigned_volunteer', $account->id())
            ->sort('updated_at', 'DESC')
            ->execute();

        $requests = $ids !== [] ? $storage->loadMultiple($ids) : [];

        $grouped = ['assigned' => [], 'in_progress' => [], 'completed' => [], 'confirmed' => []];
        foreach ($requests as $req) {
            $status = $req->get('status');
            if (isset($grouped[$status])) {
                $grouped[$status][] = $req;
            }
        }

        $volStorage = $this->entityTypeManager->getStorage('volunteer');
        $volIds = $volStorage->getQuery()->condition('account_id', $account->id())->execute();
        $volunteer = $volIds !== [] ? $volStorage->load(reset($volIds)) : null;

        $html = $this->twig->render('dashboard/volunteer.html.twig', [
            'requests' => $requests,
            'grouped' => $grouped,
            'volunteer' => $volunteer,
        ]);

        return new SsrResponse(content: $html);
    }

    public function editForm(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $volunteer = $this->findVolunteerByAccount($account);
        if ($volunteer === null) {
            return new SsrResponse(content: 'Not found', statusCode: 404);
        }

        $html = $this->twig->render('dashboard/volunteer-edit.html.twig', [
            'volunteer' => $volunteer,
        ]);

        return new SsrResponse(content: $html);
    }

    public function submitEdit(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $volunteer = $this->findVolunteerByAccount($account);
        if ($volunteer === null) {
            return new SsrResponse(content: 'Not found', statusCode: 404);
        }

        $volunteer->set('phone', trim((string) $request->request->get('phone', '')));
        $volunteer->set('availability', trim((string) $request->request->get('availability', '')));

        $maxTravelRaw = $request->request->get('max_travel_km', '');
        $maxTravelKm = $maxTravelRaw !== '' ? (int) $maxTravelRaw : null;
        if ($maxTravelKm !== null && ($maxTravelKm < 1 || $maxTravelKm > 1000)) {
            $maxTravelKm = null;
        }
        $volunteer->set('max_travel_km', $maxTravelKm);

        $allowedSkills = ['Rides', 'Groceries', 'Chores', 'Visits / Companionship'];
        $skills = array_values(array_intersect($request->request->all('skills'), $allowedSkills));
        $volunteer->set('skills', $skills);

        $volunteer->set('notes', trim((string) $request->request->get('notes', '')));
        $volunteer->set('updated_at', time());

        $storage = $this->entityTypeManager->getStorage('volunteer');
        $storage->save($volunteer);

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/volunteer']);
    }

    public function toggleAvailability(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $volunteer = $this->findVolunteerByAccount($account);
        if ($volunteer === null) {
            return new SsrResponse(content: 'Not found', statusCode: 404);
        }

        $newStatus = $volunteer->get('status') === 'active' ? 'unavailable' : 'active';
        $volunteer->set('status', $newStatus);
        $volunteer->set('updated_at', time());

        $storage = $this->entityTypeManager->getStorage('volunteer');
        $storage->save($volunteer);

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/volunteer']);
    }

    private function findVolunteerByAccount(AccountInterface $account): ?\Waaseyaa\Entity\ContentEntityBase
    {
        $storage = $this->entityTypeManager->getStorage('volunteer');
        $ids = $storage->getQuery()->condition('account_id', $account->id())->execute();
        if ($ids === []) {
            return null;
        }
        return $storage->load(reset($ids));
    }
}
