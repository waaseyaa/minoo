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

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        if ($phone === '') {
            $errors['phone'] = 'Phone number is required.';
        }

        if ($errors !== []) {
            $html = $this->twig->render('elders/volunteer.html.twig', [
                'errors' => $errors,
                'values' => compact('name', 'phone', 'availability', 'skills', 'notes'),
            ]);

            return new SsrResponse(content: $html);
        }

        $storage = $this->entityTypeManager->getStorage('volunteer');
        $entity = $storage->create([
            'name' => $name,
            'phone' => $phone,
            'availability' => $availability,
            'skills' => $skills,
            'notes' => $notes,
            'status' => 'active',
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $storage->save($entity);

        $id = $entity->id();

        return new SsrResponse(
            content: '',
            statusCode: 302,
            headers: ['Location' => '/elders/volunteer/' . $id],
        );
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function signupDetail(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $vid = (int) ($params['vid'] ?? 0);
        $storage = $this->entityTypeManager->getStorage('volunteer');
        $entity = $vid > 0 ? $storage->load($vid) : null;

        $html = $this->twig->render('elders/volunteer-confirmation.html.twig', [
            'entity' => $entity,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $entity !== null ? 200 : 404,
        );
    }
}
