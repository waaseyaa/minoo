<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\SSR\Flash\Flash;

final class VolunteerController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    private const int MAX_TRAVEL_FLOOR = 1;
    private const int MAX_TRAVEL_CEILING = 1000;

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function signupForm(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        if ($account->isAuthenticated() && $this->hasExistingVolunteer($account)) {
            Flash::info("You're already registered as a volunteer.");
            return new RedirectResponse('/dashboard/volunteer');
        }

        $location = $this->resolveLocation($request);

        $html = $this->twig->render('elders/volunteer.html.twig', LayoutTwigContext::withAccount($account, [
            'errors' => [],
            'values' => [],
            'location' => $location,
        ]));

        return new Response($html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function submitSignup(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $name = trim((string) $request->request->get('name', ''));
        $phone = trim((string) $request->request->get('phone', ''));
        $availability = trim((string) $request->request->get('availability', ''));
        $allowedSkills = ['Rides', 'Groceries', 'Chores', 'Visits / Companionship'];
        $skills = array_values(array_intersect($request->request->all('skills'), $allowedSkills));
        $community = trim((string) $request->request->get('community', ''));
        $notes = trim((string) $request->request->get('notes', ''));
        $maxTravelRaw = $request->request->get('max_travel_km', '');
        $maxTravelKm = $maxTravelRaw !== '' ? (int) $maxTravelRaw : null;

        if ($account->isAuthenticated() && $this->hasExistingVolunteer($account)) {
            Flash::info("You're already registered as a volunteer.");
            return new RedirectResponse('/dashboard/volunteer');
        }

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        if ($phone === '') {
            $errors['phone'] = 'Phone number is required.';
        }
        if ($phone !== '' && !$account->isAuthenticated() && $this->phoneExists($phone)) {
            $errors['phone'] = 'This phone number is already registered. Please <a href="/login">sign in</a> to manage your volunteer profile.';
        }
        if ($maxTravelKm !== null && ($maxTravelKm < self::MAX_TRAVEL_FLOOR || $maxTravelKm > self::MAX_TRAVEL_CEILING)) {
            $maxTravelKm = null;
        }

        if ($errors !== []) {
            $html = $this->twig->render('elders/volunteer.html.twig', LayoutTwigContext::withAccount($account, [
                'errors' => $errors,
                'values' => compact('name', 'phone', 'community', 'availability', 'skills', 'notes', 'maxTravelKm'),
            ]));

            return new Response($html, 422);
        }

        $storage = $this->entityTypeManager->getStorage('volunteer');
        $values = [
            'name' => $name,
            'phone' => $phone,
            'community' => $community,
            'availability' => $availability,
            'skills' => $skills,
            'notes' => $notes,
            'status' => 'pending',
            'created_at' => time(),
            'updated_at' => time(),
        ];
        if ($maxTravelKm !== null) {
            $values['max_travel_km'] = $maxTravelKm;
        }
        if ($account->isAuthenticated()) {
            $values['account_id'] = $account->id();
        }
        $entity = $storage->create($values);
        $storage->save($entity);

        return new RedirectResponse('/elders/volunteer/' . $entity->uuid());
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function signupDetail(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $uuid = $params['uuid'] ?? '';
        $entity = null;

        if ($uuid !== '') {
            $storage = $this->entityTypeManager->getStorage('volunteer');
            $ids = $storage->getQuery()->condition('uuid', $uuid)->execute();
            if ($ids !== []) {
                $entity = $storage->load(reset($ids));
            }
        }

        $html = $this->twig->render('elders/volunteer-confirmation.html.twig', LayoutTwigContext::withAccount($account, [
            'entity' => $entity,
        ]));

        return new Response($html, $entity !== null ? 200 : 404);
    }

    private function hasExistingVolunteer(AccountInterface $account): bool
    {
        $storage = $this->entityTypeManager->getStorage('volunteer');
        $ids = $storage->getQuery()->condition('account_id', $account->id())->execute();
        return $ids !== [];
    }

    private function phoneExists(string $phone): bool
    {
        $storage = $this->entityTypeManager->getStorage('volunteer');
        $ids = $storage->getQuery()->condition('phone', $phone)->execute();
        return $ids !== [];
    }

    private function resolveLocation(HttpRequest $request): \App\Domain\Geo\ValueObject\LocationContext
    {
        return (new \App\Service\LocationResolver(
            $this->entityTypeManager,
            new \App\Domain\Geo\Service\CommunityFinder(),
        ))->resolveLocation($request);
    }
}
