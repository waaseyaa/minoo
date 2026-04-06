<?php

declare(strict_types=1);

namespace Minoo\Controller;

use Minoo\Domain\Newsletter\Exception\DispatchException;
use Minoo\Domain\Newsletter\Exception\RenderException;
use Minoo\Domain\Newsletter\Service\EditionLifecycle;
use Minoo\Domain\Newsletter\Service\NewsletterAssembler;
use Minoo\Domain\Newsletter\Service\NewsletterDispatcher;
use Minoo\Domain\Newsletter\Service\NewsletterRenderer;
use Minoo\Support\Flash;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class NewsletterEditorController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly EditionLifecycle $lifecycle,
        private readonly NewsletterAssembler $assembler,
        private readonly NewsletterRenderer $renderer,
        private readonly NewsletterDispatcher $dispatcher,
    ) {
    }

    public function list(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $editions = $storage->loadMultiple();

        return new Response($this->twig->render('newsletter/editor/list.html.twig', [
            'editions' => $editions,
            'flashes' => Flash::pull(),
        ]));
    }

    public function create(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $communityId = $request->request->get('community_id') ?: null;
        $publishDate = (string) $request->request->get('publish_date');

        $storage = $this->entityTypeManager->getStorage('newsletter_edition');

        // Auto-assign volume + issue number for this community.
        $existingForCommunity = array_filter(
            $storage->loadMultiple(),
            fn ($e) => (string) $e->get('community_id') === (string) $communityId,
        );
        $maxIssue = 0;
        foreach ($existingForCommunity as $e) {
            $maxIssue = max($maxIssue, (int) $e->get('issue_number'));
        }
        $nextIssue = $maxIssue + 1;

        $edition = $storage->create([
            'community_id' => $communityId,
            'volume' => 1,
            'issue_number' => $nextIssue,
            'publish_date' => $publishDate,
            'status' => 'draft',
            'created_by' => $account->id(),
            'headline' => sprintf('Issue %d', $nextIssue),
        ]);
        $storage->save($edition);

        Flash::success('New newsletter edition created.');

        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }

    public function assemble(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        $this->assembler->assemble($edition);

        $this->entityTypeManager->getStorage('newsletter_edition')->save($edition);

        if ((string) $edition->get('status') === 'draft') {
            Flash::error('No content found for this date window. Try again after submissions arrive.');
        } else {
            Flash::success('Edition assembled — review the queue.');
        }

        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }

    public function show(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        $itemStorage = $this->entityTypeManager->getStorage('newsletter_item');
        $items = array_filter(
            $itemStorage->loadMultiple(),
            fn ($i) => (int) $i->get('edition_id') === (int) $edition->id(),
        );

        $bySection = [];
        foreach ($items as $item) {
            $bySection[(string) $item->get('section')][] = $item;
        }

        return new Response($this->twig->render('newsletter/editor/newsroom.html.twig', [
            'edition' => $edition,
            'items_by_section' => $bySection,
            'flashes' => Flash::pull(),
        ]));
    }

    public function approve(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        try {
            $this->lifecycle->approve($edition, (int) $account->id());
            $this->entityTypeManager->getStorage('newsletter_edition')->save($edition);
            Flash::success('Edition approved. You can now generate the PDF.');
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());
        }

        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }

    public function generate(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        try {
            $artifact = $this->renderer->render($edition);
            $this->lifecycle->markGenerated($edition, $artifact->path, $artifact->sha256);
            $this->entityTypeManager->getStorage('newsletter_edition')->save($edition);
            Flash::success(sprintf('PDF generated (%d bytes).', $artifact->bytes));
        } catch (RenderException $e) {
            Flash::error('Render failed: ' . $e->getMessage());
        } catch (\DomainException $e) {
            Flash::error('This edition cannot be generated in its current state. Refresh and try again.');
        }

        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }

    public function send(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $edition = $this->loadEditionOrFail($params['id'] ?? null);

        try {
            $recipient = $this->dispatcher->dispatch($edition);

            $this->lifecycle->markSent($edition);
            $this->entityTypeManager->getStorage('newsletter_edition')->save($edition);

            $this->writeIngestLog(
                title: sprintf('Newsletter edition #%d sent to %s', (int) $edition->id(), $recipient),
                success: true,
                message: sprintf('Dispatched PDF to %s', $recipient),
            );

            Flash::success(sprintf('Newsletter sent to %s.', $recipient));
        } catch (DispatchException $e) {
            $this->writeIngestLog(
                title: sprintf('Newsletter edition #%d dispatch failed', (int) $edition->id()),
                success: false,
                message: $e->getMessage(),
            );
            Flash::error('Send failed: ' . $e->getMessage());
        } catch (\DomainException $e) {
            Flash::error('This edition cannot be sent in its current state. Refresh and try again.');
        }

        return new RedirectResponse('/coordinator/newsletter/' . $edition->id());
    }

    public function submissionsList(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $coordinatorCommunity = $this->resolveCoordinatorCommunity($request);

        $storage = $this->entityTypeManager->getStorage('newsletter_submission');
        $pending = array_filter(
            $storage->loadMultiple(),
            fn ($s) => (string) $s->get('status') === 'submitted'
                && (string) $s->get('community_id') === $coordinatorCommunity,
        );

        return new Response($this->twig->render('newsletter/editor/submissions.html.twig', [
            'submissions' => $pending,
            'flashes' => Flash::pull(),
        ]));
    }

    public function submissionApprove(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_submission');
        $sub = $storage->load((int) ($params['id'] ?? 0));
        if ($sub === null) {
            return new Response('Not found', 404);
        }
        if (! $this->coordinatorOwnsSubmission($sub, $request)) {
            return new Response('Forbidden', 403);
        }
        $sub->set('status', 'approved');
        $sub->set('approved_by', $account->id());
        $sub->set('approved_at', date(\DateTimeInterface::ATOM));
        $storage->save($sub);

        Flash::success('Submission approved.');

        return new RedirectResponse('/coordinator/newsletter/submissions');
    }

    public function submissionReject(array $params, array $query, AccountInterface $account, HttpRequest $request): Response
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_submission');
        $sub = $storage->load((int) ($params['id'] ?? 0));
        if ($sub === null) {
            return new Response('Not found', 404);
        }
        if (! $this->coordinatorOwnsSubmission($sub, $request)) {
            return new Response('Forbidden', 403);
        }
        $sub->set('status', 'rejected');
        $sub->set('approved_by', $account->id());
        $sub->set('approved_at', date(\DateTimeInterface::ATOM));
        $storage->save($sub);

        Flash::success('Submission rejected.');

        return new RedirectResponse('/coordinator/newsletter/submissions');
    }

    /**
     * Resolve the "current coordinator's community" for tenancy scoping.
     *
     * Option B (per code review of #648): there is no coordinator→community
     * mapping helper in the codebase yet, and the v1 config defines a single
     * community (`manitoulin-regional`). We resolve the coordinator's community
     * using the same cookie + config fallback that `NewsletterController::submitPost`
     * uses for submitters, so a coordinator's view of the moderation queue is
     * scoped to whichever community context they are currently in. The cookie
     * value is validated against the configured community list — an unknown or
     * missing cookie falls back to `default_community`. When per-coordinator
     * community membership lands (multi-community v2), replace this with a
     * proper account→community helper.
     */
    private function resolveCoordinatorCommunity(HttpRequest $request): string
    {
        $config = require dirname(__DIR__, 2) . '/config/newsletter.php';
        $validCommunities = array_keys($config['communities'] ?? []);
        $cookieCommunity = (string) ($request->cookies->get('community_id') ?? '');

        return in_array($cookieCommunity, $validCommunities, true)
            ? $cookieCommunity
            : ($config['default_community'] ?? 'manitoulin-regional');
    }

    private function coordinatorOwnsSubmission(EntityInterface $sub, HttpRequest $request): bool
    {
        return (string) $sub->get('community_id') === $this->resolveCoordinatorCommunity($request);
    }

    private function writeIngestLog(string $title, bool $success, string $message): void
    {
        try {
            $storage = $this->entityTypeManager->getStorage('ingest_log');
            $log = $storage->create([
                'title' => $title,
                'status' => $success ? 'approved' : 'failed',
                'source' => 'newsletter_dispatch',
                'error_message' => $success ? '' : $message,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
            $storage->save($log);
        } catch (\Throwable) {
            // Audit logging is best-effort: never let it block the user flow.
        }
    }

    private function loadEditionOrFail(mixed $id): EntityInterface
    {
        $storage = $this->entityTypeManager->getStorage('newsletter_edition');
        $edition = $storage->load((int) $id);
        if ($edition === null) {
            throw new \RuntimeException('Newsletter edition not found.');
        }

        return $edition;
    }
}
