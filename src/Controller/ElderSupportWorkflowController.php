<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Support\Flash;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class ElderSupportWorkflowController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function assignVolunteer(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        if (!in_array('elder_coordinator', $account->getRoles(), true) && !$account->hasPermission('administer content')) {
            return new SsrResponse(content: 'Forbidden', statusCode: 403);
        }

        $esrid = (int) ($params['esrid'] ?? 0);
        $volunteerId = (int) $request->request->get('volunteer_id', 0);

        $storage = $this->entityTypeManager->getStorage('elder_support_request');
        $entity = $esrid > 0 ? $storage->load($esrid) : null;

        if ($entity === null) {
            return new SsrResponse(content: 'Not found', statusCode: 404);
        }

        $volunteerStorage = $this->entityTypeManager->getStorage('volunteer');
        $volunteer = $volunteerId > 0 ? $volunteerStorage->load($volunteerId) : null;

        if ($volunteer === null) {
            return new SsrResponse(content: 'Volunteer not found', statusCode: 404);
        }

        $entity->set('assigned_volunteer', $volunteerId);
        $entity->set('assigned_at', time());
        $entity->set('status', 'assigned');
        $entity->set('updated_at', time());
        $storage->save($entity);

        Flash::set('success', 'Volunteer assigned successfully.');
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator']);
    }

    public function startRequest(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        return $this->volunteerTransition(
            $params,
            $account,
            'assigned',
            'in_progress',
            'Request marked as in progress.',
        );
    }

    public function completeRequest(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        return $this->volunteerTransition(
            $params,
            $account,
            'in_progress',
            'completed',
            'Request marked as complete. The coordinator will follow up.',
        );
    }

    public function confirmRequest(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        if (!in_array('elder_coordinator', $account->getRoles(), true) && !$account->hasPermission('administer content')) {
            return new SsrResponse(content: 'Forbidden', statusCode: 403);
        }

        $esrid = (int) ($params['esrid'] ?? 0);
        $storage = $this->entityTypeManager->getStorage('elder_support_request');
        $entity = $esrid > 0 ? $storage->load($esrid) : null;

        if ($entity === null) {
            return new SsrResponse(content: 'Not found', statusCode: 404);
        }

        if ($entity->get('status') !== 'completed') {
            return new SsrResponse(content: 'Invalid status transition', statusCode: 422);
        }

        $entity->set('status', 'confirmed');
        $entity->set('updated_at', time());
        $storage->save($entity);

        Flash::set('success', 'Request marked as confirmed. Thank you for following up.');
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator']);
    }

    public function cancelRequest(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        if (!in_array('elder_coordinator', $account->getRoles(), true) && !$account->hasPermission('administer content')) {
            return new SsrResponse(content: 'Forbidden', statusCode: 403);
        }

        $esrid = (int) ($params['esrid'] ?? 0);
        $storage = $this->entityTypeManager->getStorage('elder_support_request');
        $entity = $esrid > 0 ? $storage->load($esrid) : null;

        if ($entity === null) {
            return new SsrResponse(content: 'Not found', statusCode: 404);
        }

        $status = $entity->get('status');
        if (!in_array($status, ['open', 'assigned'], true)) {
            return new SsrResponse(content: 'Invalid status transition', statusCode: 422);
        }

        $reason = trim((string) $request->request->get('reason', ''));

        $entity->set('status', 'cancelled');
        $entity->set('cancelled_reason', $reason);
        $entity->set('updated_at', time());
        $storage->save($entity);

        Flash::set('success', 'Request cancelled.');
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator']);
    }

    public function declineRequest(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $esrid = (int) ($params['esrid'] ?? 0);
        $storage = $this->entityTypeManager->getStorage('elder_support_request');
        $entity = $esrid > 0 ? $storage->load($esrid) : null;

        if ($entity === null) {
            return new SsrResponse(content: 'Not found', statusCode: 404);
        }

        if ($entity->get('assigned_volunteer') !== $account->id()) {
            return new SsrResponse(content: 'Forbidden', statusCode: 403);
        }

        if ($entity->get('status') !== 'assigned') {
            return new SsrResponse(content: 'Invalid status transition', statusCode: 422);
        }

        $entity->set('status', 'open');
        $entity->set('assigned_volunteer', null);
        $entity->set('assigned_at', null);
        $entity->set('updated_at', time());
        $storage->save($entity);

        Flash::set('success', 'Request declined. The coordinator has been notified.');
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/volunteer']);
    }

    public function reassignVolunteer(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        if (!in_array('elder_coordinator', $account->getRoles(), true) && !$account->hasPermission('administer content')) {
            return new SsrResponse(content: 'Forbidden', statusCode: 403);
        }

        $esrid = (int) ($params['esrid'] ?? 0);
        $volunteerId = (int) $request->request->get('volunteer_id', 0);

        $storage = $this->entityTypeManager->getStorage('elder_support_request');
        $entity = $esrid > 0 ? $storage->load($esrid) : null;

        if ($entity === null) {
            return new SsrResponse(content: 'Not found', statusCode: 404);
        }

        $volunteerStorage = $this->entityTypeManager->getStorage('volunteer');
        $volunteer = $volunteerId > 0 ? $volunteerStorage->load($volunteerId) : null;

        if ($volunteer === null) {
            return new SsrResponse(content: 'Volunteer not found', statusCode: 404);
        }

        $entity->set('assigned_volunteer', $volunteerId);
        $entity->set('assigned_at', time());
        $entity->set('status', 'assigned');
        $entity->set('updated_at', time());
        $storage->save($entity);

        Flash::set('success', 'Request reassigned.');
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/coordinator']);
    }

    private function volunteerTransition(
        array $params,
        AccountInterface $account,
        string $fromStatus,
        string $toStatus,
        string $message,
    ): SsrResponse
    {
        $esrid = (int) ($params['esrid'] ?? 0);
        $storage = $this->entityTypeManager->getStorage('elder_support_request');
        $entity = $esrid > 0 ? $storage->load($esrid) : null;

        if ($entity === null) {
            return new SsrResponse(content: 'Not found', statusCode: 404);
        }

        if ($entity->get('assigned_volunteer') !== $account->id()) {
            return new SsrResponse(content: 'Forbidden', statusCode: 403);
        }

        if ($entity->get('status') !== $fromStatus) {
            return new SsrResponse(content: 'Invalid status transition', statusCode: 422);
        }

        $entity->set('status', $toStatus);
        $entity->set('updated_at', time());
        $storage->save($entity);

        Flash::set('success', $message);
        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/dashboard/volunteer']);
    }
}
