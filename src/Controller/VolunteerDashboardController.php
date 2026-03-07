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

        $html = $this->twig->render('dashboard/volunteer.html.twig', [
            'requests' => $requests,
            'grouped' => $grouped,
        ]);

        return new SsrResponse(content: $html);
    }
}
