<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

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
    public function signupForm(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('elders/volunteer.html.twig', [
            'errors' => [],
            'values' => [],
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function submitSignup(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $name = trim((string) $request->request->get('name', ''));
        $phone = trim((string) $request->request->get('phone', ''));
        $availability = trim((string) $request->request->get('availability', ''));
        $allowedSkills = ['Rides', 'Groceries', 'Chores', 'Visits / Companionship'];
        $skills = array_values(array_intersect($request->request->all('skills'), $allowedSkills));
        $notes = trim((string) $request->request->get('notes', ''));
        $maxTravelRaw = $request->request->get('max_travel_km', '');
        $maxTravelKm = $maxTravelRaw !== '' ? (int) $maxTravelRaw : null;

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        if ($phone === '') {
            $errors['phone'] = 'Phone number is required.';
        }
        if ($maxTravelKm !== null && ($maxTravelKm < self::MAX_TRAVEL_FLOOR || $maxTravelKm > self::MAX_TRAVEL_CEILING)) {
            $maxTravelKm = null;
        }

        if ($errors !== []) {
            $html = $this->twig->render('elders/volunteer.html.twig', [
                'errors' => $errors,
                'values' => compact('name', 'phone', 'availability', 'skills', 'notes', 'maxTravelKm'),
            ]);

            return new SsrResponse(content: $html, statusCode: 422);
        }

        $storage = $this->entityTypeManager->getStorage('volunteer');
        $values = [
            'name' => $name,
            'phone' => $phone,
            'availability' => $availability,
            'skills' => $skills,
            'notes' => $notes,
            'status' => 'active',
            'created_at' => time(),
            'updated_at' => time(),
        ];
        if ($maxTravelKm !== null) {
            $values['max_travel_km'] = $maxTravelKm;
        }
        $values['account_id'] = $account->id();
        $entity = $storage->create($values);
        $storage->save($entity);

        return new SsrResponse(
            content: '',
            statusCode: 302,
            headers: ['Location' => '/elders/volunteer/' . $entity->uuid()],
        );
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function signupDetail(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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

        $html = $this->twig->render('elders/volunteer-confirmation.html.twig', [
            'entity' => $entity,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $entity !== null ? 200 : 404,
        );
    }
}
