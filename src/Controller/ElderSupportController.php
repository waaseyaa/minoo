<?php

declare(strict_types=1);

namespace App\Controller;

use Waaseyaa\SSR\Flash\Flash;
use App\Support\LayoutTwigContext;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class ElderSupportController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function requestForm(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $location = $this->resolveLocation($request);

        $html = $this->twig->render('pages/elders/request.html.twig', LayoutTwigContext::withAccount($account, [
            'errors' => [],
            'values' => [],
            'location' => $location,
        ]));

        return new Response($html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function submitRequest(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $name = trim((string) $request->request->get('name', ''));
        $phone = trim((string) $request->request->get('phone', ''));
        $community = trim((string) $request->request->get('community', ''));
        $type = trim((string) $request->request->get('type', ''));
        $notes = trim((string) $request->request->get('notes', ''));
        $isRepresentative = $request->request->get('is_representative') === '1';
        $elderName = trim((string) $request->request->get('elder_name', ''));
        $consent = $request->request->get('consent') === '1';

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        if ($phone === '') {
            $errors['phone'] = 'Phone number is required.';
        }
        if (!in_array($type, ['ride', 'groceries', 'chores', 'visit'], true)) {
            $errors['type'] = 'Please select a request type.';
        }
        if ($isRepresentative) {
            if ($elderName === '') {
                $errors['elder_name'] = 'Elder\'s name is required when requesting on their behalf.';
            }
            if (!$consent) {
                $errors['consent'] = 'Please confirm the Elder has given permission.';
            }
        }

        if ($errors !== []) {
            $html = $this->twig->render('pages/elders/request.html.twig', LayoutTwigContext::withAccount($account, [
                'errors' => $errors,
                'values' => compact('name', 'phone', 'community', 'type', 'notes', 'isRepresentative', 'elderName', 'consent'),
            ]));

            return new Response($html, 422);
        }

        $storage = $this->entityTypeManager->getStorage('elder_support_request');
        $entity = $storage->create([
            'name' => $name,
            'phone' => $phone,
            'community' => $community,
            'type' => $type,
            'notes' => $notes,
            'is_representative' => $isRepresentative,
            'elder_name' => $isRepresentative ? $elderName : '',
            'has_consent' => $isRepresentative && $consent,
            'status' => 'open',
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $storage->save($entity);

        Flash::success('Your request has been submitted. A coordinator will be in touch.');

        return new RedirectResponse('/elders/request/' . $entity->uuid());
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function requestDetail(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $uuid = $params['uuid'] ?? '';
        $entity = null;

        if ($uuid !== '') {
            $storage = $this->entityTypeManager->getStorage('elder_support_request');
            $ids = $storage->getQuery()->condition('uuid', $uuid)->execute();
            if ($ids !== []) {
                $entity = $storage->load(reset($ids));
            }
        }

        $html = $this->twig->render('pages/elders/request-confirmation.html.twig', LayoutTwigContext::withAccount($account, [
            'entity' => $entity,
        ]));

        return new Response($html, $entity !== null ? 200 : 404);
    }

    private function resolveLocation(HttpRequest $request): \App\Domain\Geo\ValueObject\LocationContext
    {
        return (new \App\Service\LocationResolver(
            $this->entityTypeManager,
            new \App\Domain\Geo\Service\CommunityFinder(),
        ))->resolveLocation($request);
    }
}
