<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class ElderSupportController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function requestForm(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('elders/request.html.twig', [
            'errors' => [],
            'values' => [],
        ]);

        return new SsrResponse(content: $html);
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function submitRequest(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $name = trim((string) $request->request->get('name', ''));
        $phone = trim((string) $request->request->get('phone', ''));
        $community = trim((string) $request->request->get('community', ''));
        $type = trim((string) $request->request->get('type', ''));
        $notes = trim((string) $request->request->get('notes', ''));

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

        if ($errors !== []) {
            $html = $this->twig->render('elders/request.html.twig', [
                'errors' => $errors,
                'values' => compact('name', 'phone', 'community', 'type', 'notes'),
            ]);

            return new SsrResponse(content: $html, statusCode: 422);
        }

        $storage = $this->entityTypeManager->getStorage('elder_support_request');
        $entity = $storage->create([
            'name' => $name,
            'phone' => $phone,
            'community' => $community,
            'type' => $type,
            'notes' => $notes,
            'status' => 'open',
            'created_at' => time(),
            'updated_at' => time(),
        ]);
        $storage->save($entity);

        return new SsrResponse(
            content: '',
            statusCode: 302,
            headers: ['Location' => '/elders/request/' . $entity->uuid()],
        );
    }

    /** @param array<string, mixed> $params */
    /** @param array<string, mixed> $query */
    public function requestDetail(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
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

        $html = $this->twig->render('elders/request-confirmation.html.twig', [
            'entity' => $entity,
        ]);

        return new SsrResponse(
            content: $html,
            statusCode: $entity !== null ? 200 : 404,
        );
    }
}
