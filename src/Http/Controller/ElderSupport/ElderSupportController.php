<?php

declare(strict_types=1);

namespace App\Http\Controller\ElderSupport;

use App\Domain\Community\MamaweswenNations;
use App\Http\View\LayoutTwigContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;
use Waaseyaa\SSR\Flash\Flash;

/**
 * Elder-support request workflow (#801). Any signed-in member can submit a
 * request; community coordinators and admins triage them from a gated inbox.
 * Not surfaced in public nav — going live as a public intake needs a staffed
 * coordinator program + a privacy review (it collects personal contact info).
 */
final class ElderSupportController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly EntityTypeManager $entityTypeManager,
    ) {
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function form(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $html = $this->twig->render('pages/elder-support/form.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/elder-support',
            'communities' => MamaweswenNations::all(),
            'can_triage' => $this->canTriage($account),
        ]));

        return new Response($html);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function submit(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $name = trim((string) $request->request->get('name', ''));
        $message = trim((string) $request->request->get('message', ''));

        if ($name === '' || $message === '') {
            Flash::error('Please give a name and describe what support is needed.');
            return new RedirectResponse('/elder-support');
        }

        $repository = $this->entityTypeManager->getRepository('elder_support_request');
        $now = time();
        $entity = $repository->create([
            'name' => $name,
            'community' => trim((string) $request->request->get('community', '')),
            'support_type' => trim((string) $request->request->get('support_type', '')),
            'message' => $message,
            'contact' => trim((string) $request->request->get('contact', '')),
            'status' => 'open',
            'assigned_to' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $repository->save($entity);

        Flash::success('Miigwech. Your request has been sent to the community coordinators.');

        return new RedirectResponse('/elder-support');
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $query */
    public function inbox(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $request): Response
    {
        if (!$this->canTriage($account)) {
            return new Response('Forbidden', 403);
        }

        $repository = $this->entityTypeManager->getRepository('elder_support_request');
        $ids = $repository->getQuery()->setAccount($account)
            ->sort('created_at', 'DESC')
            ->range(0, 200)
            ->execute();

        $requests = [];
        if ($ids !== []) {
            foreach ($repository->findMany($ids) as $req) {
                $requests[] = [
                    'name' => (string) $req->get('name'),
                    'community' => (string) $req->get('community'),
                    'support_type' => (string) $req->get('support_type'),
                    'message' => (string) $req->get('message'),
                    'contact' => (string) $req->get('contact'),
                    'status' => (string) $req->get('status'),
                    'created_at' => (int) $req->get('created_at'),
                ];
            }
        }

        $html = $this->twig->render('pages/elder-support/inbox.html.twig', LayoutTwigContext::withAccount($account, [
            'path' => '/elder-support/requests',
            'requests' => $requests,
        ]));

        return new Response($html);
    }

    private function canTriage(AccountInterface $account): bool
    {
        return $account->hasPermission('administer content')
            || in_array('elder_coordinator', $account->getRoles(), true);
    }
}
