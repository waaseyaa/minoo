<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;
use Waaseyaa\SSR\Flash\Flash;

final class VolunteerDashboardController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {
    }

    public function index(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
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
        $html = $this->twig->render('pages/dashboard/volunteer.html.twig', LayoutTwigContext::withAccount($account, [
            'requests' => $requests,
            'grouped' => $grouped,
            'volunteer' => $volunteer,
        ]));

        return new Response($html);
    }

    public function editForm(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $volunteer = $this->findVolunteerByAccount($account);
        if ($volunteer === null) {
            return new Response('Not found', 404);
        }

        $html = $this->twig->render('pages/dashboard/volunteer-edit.html.twig', LayoutTwigContext::withAccount($account, [
            'volunteer' => $volunteer,
            'errors' => [],
            'values' => [],
        ]));

        return new Response($html);
    }

    public function submitEdit(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $volunteer = $this->findVolunteerByAccount($account);
        if ($volunteer === null) {
            return new Response('Not found', 404);
        }

        $phone = trim((string) $request->request->get('phone', ''));

        $errors = [];
        if ($phone === '') {
            $errors['phone'] = 'Phone number is required.';
        }

        if ($errors !== []) {
            $html = $this->twig->render('pages/dashboard/volunteer-edit.html.twig', LayoutTwigContext::withAccount($account, [
                'volunteer' => $volunteer,
                'errors' => $errors,
                'values' => [
                    'phone' => $phone,
                    'availability' => trim((string) $request->request->get('availability', '')),
                    'max_travel_km' => $request->request->get('max_travel_km', ''),
                    'skills' => $request->request->all('skills'),
                    'notes' => trim((string) $request->request->get('notes', '')),
                ],
            ]));
            return new Response($html, 422);
        }

        $volunteer->set('phone', $phone);
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

        Flash::success('Your profile has been updated.');
        return new RedirectResponse('/dashboard/volunteer');
    }

    public function toggleAvailability(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $volunteer = $this->findVolunteerByAccount($account);
        if ($volunteer === null) {
            return new Response('Not found', 404);
        }

        $newStatus = $volunteer->get('status') === 'active' ? 'unavailable' : 'active';
        $volunteer->set('status', $newStatus);
        $volunteer->set('updated_at', time());

        $storage = $this->entityTypeManager->getStorage('volunteer');
        $storage->save($volunteer);

        $message = $newStatus === 'active' ? 'You are now active.' : 'You are now unavailable.';
        Flash::success($message);
        return new RedirectResponse('/dashboard/volunteer');
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
