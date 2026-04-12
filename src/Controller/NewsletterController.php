<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Newsletter\Service\RenderTokenStore;
use Waaseyaa\SSR\Flash\Flash;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class NewsletterController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly RenderTokenStore $tokens,
    ) {}

    /**
     * Internal endpoint hit by Playwright during PDF generation.
     * Public route, but requires a single-use one-time token.
     */
    public function printPreview(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $token = (string) $request->query->get('token', '');
        $editionId = (int) ($params['id'] ?? 0);

        if (! $this->tokens->consume($token, $editionId)) {
            return new Response('Gone', 410);
        }

        $editionStorage = $this->entityTypeManager->getStorage('newsletter_edition');
        $edition = $editionStorage->load($editionId);
        if ($edition === null) {
            return new Response('Not found', 404);
        }

        $itemStorage = $this->entityTypeManager->getStorage('newsletter_item');
        $items = array_filter(
            $itemStorage->loadMultiple(),
            fn($i) => (int) $i->get('edition_id') === $editionId,
        );

        $bySection = [];
        $sourceEntities = [];
        foreach ($items as $item) {
            $bySection[(string) $item->get('section')][] = $item;

            $srcType = (string) $item->get('source_type');
            $srcId = (int) $item->get('source_id');
            if ($srcType !== '' && $srcId > 0) {
                $src = $this->entityTypeManager->getStorage($srcType)->load($srcId);
                if ($src !== null) {
                    $sourceEntities[$item->id()] = $src;
                }
            }
        }

        return new Response($this->twig->render('newsletter/edition.html.twig', [
            'edition' => $edition,
            'items_by_section' => $bySection,
            'source_entities' => $sourceEntities,
        ]));
    }

    public function index(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $editions = array_filter(
            $storage->loadMultiple(),
            fn ($e) => in_array((string) $e->get('status'), ['generated', 'sent'], true),
        );

        $byCommunity = [];
        foreach ($editions as $e) {
            $byCommunity[(string) ($e->get('community_id') ?: 'regional')][] = $e;
        }

        return new Response($this->twig->render('newsletter/public/list.html.twig', [
            'editions_by_community' => $byCommunity,
        ]));
    }

    public function showCommunity(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $community = (string) ($params['community'] ?? '');
        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $editions = array_filter(
            $storage->loadMultiple(),
            fn ($e) =>
                (string) ($e->get('community_id') ?: 'regional') === $community &&
                in_array((string) $e->get('status'), ['generated', 'sent'], true),
        );

        usort($editions, fn ($a, $b) => (int) $b->get('issue_number') <=> (int) $a->get('issue_number'));

        return new Response($this->twig->render('newsletter/public/list.html.twig', [
            'community' => $community,
            'editions' => $editions,
        ]));
    }

    public function showEdition(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadPublicEdition($params);
        if ($edition === null) {
            return new Response('Not found', 404);
        }

        return new Response($this->twig->render('newsletter/public/edition.html.twig', [
            'edition' => $edition,
        ]));
    }

    public function downloadPdf(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadPublicEdition($params);
        if ($edition === null) {
            return new Response('Not found', 404);
        }
        $path = (string) $edition->get('pdf_path');
        if ($path === '' || ! is_file($path)) {
            return new Response('PDF not generated', 404);
        }

        return new BinaryFileResponse($path, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                'inline; filename="%s-%d-%d.pdf"',
                $params['community'] ?? 'regional',
                (int) $edition->get('volume'),
                (int) $edition->get('issue_number'),
            ),
        ]);
    }

    public function submitForm(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        if (! $account->isAuthenticated()) {
            return new RedirectResponse('/login?redirect=/newsletter/submit');
        }

        return new Response($this->twig->render('newsletter/public/submit.html.twig'));
    }

    public function submitPost(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        // TODO: rate-limit this endpoint explicitly. For v1 it inherits any
        // global rate limiting from RateLimitMiddleware. See #648.
        if (! $account->isAuthenticated()) {
            return new Response('Forbidden', 403);
        }

        $title = trim((string) $request->request->get('title'));
        $body = trim((string) $request->request->get('body'));
        $category = (string) $request->request->get('category', 'notice');
        $allowed = ['birthday', 'memorial', 'notice', 'recipe', 'language_tip', 'event', 'other'];

        if ($title === '' || $body === '') {
            Flash::error('Title and body are required.');
            return new RedirectResponse('/newsletter/submit');
        }
        if (mb_strlen($body, 'UTF-8') > 500) {
            Flash::error('Body must be 500 characters or fewer.');
            return new RedirectResponse('/newsletter/submit');
        }
        if (! in_array($category, $allowed, true)) {
            Flash::error('Invalid category.');
            return new RedirectResponse('/newsletter/submit');
        }

        // Validate community_id cookie against the configured community list.
        // A malicious cookie could otherwise write an arbitrary community_id
        // into the submission and bypass per-community moderation scoping.
        $config = require dirname(__DIR__, 2) . '/config/newsletter.php';
        $validCommunities = array_keys($config['communities'] ?? []);
        $cookieCommunity = (string) ($request->cookies->get('community_id') ?? '');
        $communityId = in_array($cookieCommunity, $validCommunities, true)
            ? $cookieCommunity
            : ($config['default_community'] ?? 'manitoulin-regional');

        $storage = $this->entityTypeManager->getStorage('newsletter_submission');
        $sub = $storage->create([
            'community_id' => $communityId,
            'submitted_by' => $account->id(),
            'submitted_at' => date(\DateTimeInterface::ATOM),
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'status' => 'submitted',
        ]);
        $storage->save($sub);

        Flash::success('Thank you — your submission is in the queue.');

        return new RedirectResponse('/newsletter');
    }

    private function loadPublicEdition(array $params): ?EntityInterface
    {
        $community = (string) ($params['community'] ?? '');
        $vol = (int) ($params['volume'] ?? 0);
        $issue = (int) ($params['issue'] ?? 0);

        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        foreach ($storage->loadMultiple() as $e) {
            $eCommunity = (string) ($e->get('community_id') ?: 'regional');
            if (
                $eCommunity === $community &&
                (int) $e->get('volume') === $vol &&
                (int) $e->get('issue_number') === $issue &&
                in_array((string) $e->get('status'), ['generated', 'sent'], true)
            ) {
                return $e;
            }
        }

        return null;
    }
}
